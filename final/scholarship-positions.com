import requests
from bs4 import BeautifulSoup
import time
import re
import pandas as pd

OUTPUT_KEYS = {
    'Title': 'title',
    'Country': 'country',
    'Level': 'level',
    'University or Organization': 'university',
    'Department': 'branch',
    'Link': 'institution_link',
    'Overview': 'overview',
    'Benefits': 'extra',
    'How to Apply': 'extra',
    'Emails': 'email',
    'Supervisors': 'supervisors'
}

def decode_email(encoded_string):
    r = int(encoded_string[:2], 16)
    email = ''.join([chr(int(encoded_string[i:i+2], 16) ^ r) for i in range(2, len(encoded_string), 2)])
    return email

def replace_encoded_emails(text, soup):
    email_tags = soup.find_all('a', class_='__cf_email__')
    for tag in email_tags:
        encoded_email = tag.get('data-cfemail')
        if encoded_email:
            decoded_email = decode_email(encoded_email)
            text = text.replace(f'[email protected]', decoded_email)
    return text

def find_supervisor_names(text):
    patterns = [
        r'(?:supervisor|professor|advisor|Dr\.|Prof\.)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,2})',
        r'([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,2})\s+(?:is|will be|as)\s+(?:the|your)\s+(?:supervisor|professor|advisor)',
        r'(?:contact|email)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,2})\s+for'
    ]

    supervisors = []
    for pattern in patterns:
        matches = re.findall(pattern, text)
        supervisors.extend(matches)

    return list(set(supervisors))  # Remove duplicates

def scrape_article(url):
    try:
        response = requests.get(url)
        if response.status_code != 200:
            print(f"Error fetching article: {response.status_code}")
            return None

        soup = BeautifulSoup(response.text, 'html.parser')
        article_info = {OUTPUT_KEYS['Level']: 'PhD'}  # Add PhD level for all articles

        # Extract title and country
        title_element = soup.find('h1', class_='entry-title')
        if title_element:
            full_title = title_element.text.strip()
            parts = full_title.split(',', 1)
            article_info[OUTPUT_KEYS['Title']] = parts[0].strip()
            if len(parts) > 1:
                article_info[OUTPUT_KEYS['Country']] = parts[1].strip()

        # Extract external links from the entire page
        external_links = []
        for a in soup.find_all('a', href=True):
            if not a['href'].startswith('https://scholarship-positions.com/') and not a['href'].startswith('/') and not a['href'].startswith('#'):
                external_links.append(a['href'])
        if external_links:
            article_info[OUTPUT_KEYS['Link']] = ', '.join(external_links)

        # Find entry-content
        entry_content = soup.find('div', class_='entry-content')
        if entry_content:
            # Extract Overview (first paragraph)
            first_paragraph = entry_content.find('p')
            if first_paragraph:
                article_info[OUTPUT_KEYS['Overview']] = first_paragraph.get_text(strip=True)

            # Extract information from Brief Description
            brief_description = entry_content.find('h2', string=lambda text: text and 'Brief Description' in text)
            if brief_description:
                ul = brief_description.find_next('ul')
                if ul:
                    for li in ul.find_all('li'):
                        text = li.text.strip()
                        parts = text.split(':', 1)
                        if len(parts) == 2:
                            key, value = parts
                            key = key.strip()
                            if key in OUTPUT_KEYS:
                                article_info[OUTPUT_KEYS[key]] = value.strip()

            # If Country wasn't found earlier, use 'The award can be taken in'
            if OUTPUT_KEYS['Country'] not in article_info and 'The award can be taken in' in article_info:
                article_info[OUTPUT_KEYS['Country']] = article_info['The award can be taken in']
                del article_info['The award can be taken in']

            # Extract Benefits and How to Apply (combined into 'extra')
            extra_content = ""

            benefits_keywords = ['Benefits', 'Scholarship Value', 'Award', 'Financial Support']
            benefits_header = None
            for keyword in benefits_keywords:
                benefits_header = entry_content.find(['h2', 'h3', 'h4', 'strong'],
                                                     string=lambda text: text and keyword.lower() in text.lower())
                if benefits_header:
                    break

            if benefits_header:
                extra_content += "Benefits:\n"
                for sibling in benefits_header.next_siblings:
                    if sibling.name in ['h2', 'h3', 'h4']:
                        break
                    if sibling.name in ['p', 'ul', 'ol', 'div']:
                        if sibling.name == 'p' or (sibling.name == 'div' and not sibling.find(['ul', 'ol'])):
                            extra_content += sibling.get_text(strip=True) + "\n\n"
                        elif sibling.name in ['ul', 'ol']:
                            for li in sibling.find_all('li'):
                                extra_content += "• " + li.get_text(strip=True) + "\n"
                            extra_content += "\n"

            how_to_apply = entry_content.find('h2', string=lambda text: text and 'How to Apply' in text)
            if how_to_apply:
                extra_content += "\nHow to Apply:\n"
                for sibling in how_to_apply.next_siblings:
                    if sibling.name == 'h2':
                        break
                    if sibling.name in ['p', 'ul', 'ol']:
                        if sibling.name == 'p':
                            extra_content += sibling.get_text(strip=True) + "\n\n"
                        elif sibling.name in ['ul', 'ol']:
                            for li in sibling.find_all('li'):
                                extra_content += "• " + li.get_text(strip=True) + "\n"
                            extra_content += "\n"

            if extra_content:
                # Replace decoded emails in extra content
                extra_content = replace_encoded_emails(extra_content, entry_content)
                # Replace [email protected] with email, even if letters or symbols are attached to it
                extra_content = re.sub(r'[^\s]*\[email\s*protected\][^\s]*', 'email', extra_content)
                article_info[OUTPUT_KEYS['Benefits']] = extra_content.strip()

            # Extract and decode emails
            email_pattern = r'data-cfemail="([a-fA-F0-9]+)"'
            encoded_emails = re.findall(email_pattern, str(entry_content))
            decoded_emails = [decode_email(encoded) for encoded in encoded_emails]
            if decoded_emails:
                article_info[OUTPUT_KEYS['Emails']] = ', '.join(decoded_emails)

        # Extract supervisor names
        full_text = soup.get_text()
        supervisors = find_supervisor_names(full_text)
        if supervisors:
            article_info[OUTPUT_KEYS['Supervisors']] = ', '.join(supervisors)

        # Final check to replace any remaining [email protected] with email
        for key, value in article_info.items():
            if isinstance(value, str):
                article_info[key] = re.sub(r'[^\s]*\[email\s*protected\][^\s]*', 'email', value)

        return article_info
    except Exception as e:
        print(f"Error scraping article: {e}")
        return None

def main():
    # تعداد صفحات برای اسکرپ
    num_pages = 14
    position_number = 1
    all_data = []  # لیستی برای ذخیره تمام داده‌های جمع‌آوری شده

    for page in range(1, num_pages + 1):
        url = f"https://scholarship-positions.com/category/phd-scholarships-positions/page/{page}/"
        print(f"Scraping page {page}:")

        response = requests.get(url)
        if response.status_code != 200:
            print(f"Error fetching page: {response.status_code}")
            continue

        soup = BeautifulSoup(response.text, 'html.parser')
        articles = soup.find_all('article', id=lambda x: x and x.startswith('post-'))

        for article in articles:
            title_element = article.find('h1', class_='entry-title')
            if title_element:
                link = title_element.find('a')
                if link:
                    article_url = link.get('href')
                    article_info = scrape_article(article_url)
                    if article_info:
                        print(f"{position_number}:")
                        print(f"url: {article_url}")
                        for key, value in article_info.items():
                            print(f"{key}: {value}")
                        print("\n" + "="*50 + "\n")

                        # اضافه کردن شماره موقعیت و URL به اطلاعات مقاله
                        article_info['position_number'] = position_number
                        article_info['url'] = article_url

                        all_data.append(article_info)
                        position_number += 1

        time.sleep(1)  # مکث کوتاه بین درخواست‌ها برای جلوگیری از بار اضافی روی سرور

    # تبدیل داده‌ها به دیتافریم
    df = pd.DataFrame(all_data)

    # مرتب کردن ستون‌ها
    columns_order = ['position_number', 'url'] + list(set(OUTPUT_KEYS.values()))
    df = df.reindex(columns=columns_order)

    # ذخیره دیتافریم در یک فایل CSV
    df.to_csv('scholarships_data.csv', index=False)
    print("Data has been saved to 'scholarships_data.csv'")

    # نمایش چند ردیف اول دیتافریم
    print(df.head())

if __name__ == "__main__":
    main()
