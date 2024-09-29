pip install cloudscraper==1.2.60

pip install cloudscraper beautifulsoup4 pandas

import cloudscraper
from bs4 import BeautifulSoup
from urllib.parse import urlparse, parse_qs
import re
import time
import random
import pandas as pd
import json
import os

# Initialize cloudscraper
scraper = cloudscraper.create_scraper(browser='chrome')

# Function to extract the real URL from the redirect link
def extract_real_url(url):
    parsed = urlparse(url)
    if 'clickCount.aspx' in parsed.path:
        query = parse_qs(parsed.query)
        if 'url' in query:
            return query['url'][0]
    return url

# Function to handle requests with retry and rate limiting
def make_request_with_retry(url, retries=5, backoff_factor=0.3):
    for i in range(retries):
        try:
            response = scraper.get(url)
            response.raise_for_status()
            return response
        except cloudscraper.exceptions.CloudflareChallengeError as e:
            print(f"Cloudflare challenge detected: {e}")
            # If it's a Cloudflare challenge, we might need to wait longer
            wait_time = backoff_factor * (2 ** i) + random.uniform(0, 1)
            time.sleep(wait_time)
        except Exception as e:
            print(f"Request failed: {e}")
            if i == retries - 1:
                raise

    raise Exception("Failed to retrieve the webpage after multiple retries")

# Function to extract email addresses from a given text
def extract_emails(text):
    email_pattern = r'[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}'
    return re.findall(email_pattern, text)

# Function to process and extract information from the main page
def extract_info_from_page(url, country_name):
    try:
        response = make_request_with_retry(url)
        soup = BeautifulSoup(response.content, 'html.parser')
        boxes = soup.find_all('div', class_='row py-2 course-result-row course-result-row-standard w-100 px-0 m-0')
        infos = []
        for box in boxes:
            title_tag = box.find('h3', class_='h4 text-dark')
            university_tag = box.find('a', class_='instLink text-gray-600')
            department_tag = box.find('a', class_='deptLink text-gray-600')
            overview_tag = box.find('div', class_='desc')
            position_url_tag = box.find('a', class_='courseLink text-dark')

            title = title_tag.get_text(strip=True) if title_tag else 'N/A'
            university = university_tag.get_text(strip=True) if university_tag else 'N/A'
            department = department_tag.get_text(strip=True) if department_tag else 'N/A'

            # Extract overview and remove "Read more"
            if overview_tag:
                overview_p = overview_tag.find('p')
                overview = overview_p.get_text(strip=True) if overview_p else 'N/A'
                overview = overview.replace('Read more', '').strip()
            else:
                overview = 'N/A'

            # Extract position URL and add prefix
            if position_url_tag and 'href' in position_url_tag.attrs:
                position_url = f"https://www.findamasters.com{position_url_tag['href']}"
                # Extract additional info from position page
                additional_info = extract_additional_info_from_position_page(position_url)

                # Check study type
                study_type = additional_info['study_type']
                if study_type in ['Full time']:
                    infos.append({
                        'title': title,
                        'university': university,
                        'department': department,
                        'country': country_name,
                        'overview': overview,
                        'extra': f"{study_type}, {additional_info['entry_requirements']}",
                        'funds': additional_info['funds'],
                        'institution_link': additional_info['institution_link'],
                        'level': 'master'
                    })
            else:
                continue  # Skip this item if position URL doesn't exist

        return infos
    except Exception as e:
        print(f"Error processing page {url}: {e}")
        return []

# Function to extract additional info from position page
def extract_additional_info_from_position_page(position_url):
    try:
        response = make_request_with_retry(position_url)
        soup = BeautifulSoup(response.content, 'html.parser')

        # Extract study type
        study_type_tag = soup.find('span', class_='key-info__content key-info__study-type py-2 pr-md-3 text-nowrap d-block d-md-inline-block')
        study_type = study_type_tag.get_text(strip=True) if study_type_tag else 'N/A'

        # Extract additional info
        additional_info_tags = soup.find_all('a', class_='inheritFont concealLink text-decoration-none text-gray-600')
        additional_info = [tag.get_text(strip=True) for tag in additional_info_tags]
        additional_info = ', '.join(additional_info) if additional_info else 'N/A'

        # Extract entry requirements
        entry_requirements_tag = soup.find('div', class_='course-sections course-sections__entry-requirements tight col-xs-24')
        entry_requirements = 'N/A'
        if entry_requirements_tag:
            paragraphs = entry_requirements_tag.find_all('p')
            entry_requirements = ' '.join([p.get_text(strip=True) for p in paragraphs])

        # Extract funds and resolve the final URL after redirection
        funds_tag = soup.find('div', class_='course-sections course-sections__fees tight col-xs-24')
        funds = 'N/A'
        if funds_tag:
            funds_p = funds_tag.find('p')
            funds_a = funds_tag.find('a', class_='noWrap inheritFont')
            if funds_a and 'href' in funds_a.attrs:
                funds_url = funds_a['href']
                # Resolve the final URL after redirection
                funds = resolve_redirect_url(funds_url)
                # Use the extract_real_url function to get the real URL
                funds = extract_real_url(funds)
            elif funds_p:
                funds = funds_p.get_text(strip=True)

        # Extract institution link
        institution_link = 'N/A'
        sidebar_buttons = soup.find('div', class_='course-sidebar__buttons px-3 px-md-0')
        if sidebar_buttons:
            a_tag = sidebar_buttons.find('a', href=True)
            if a_tag and 'href' in a_tag.attrs:
                institution_link = a_tag['href']
                institution_link = extract_real_url(institution_link)

        # Extract email addresses
        page_content = soup.find('div', class_='page-content col-24 col-md-16 col-lg-17 px-0 px-md-auto pr-lg-3')
        emails = 'N/A'
        if page_content:
            text = page_content.get_text()
            email_addresses = extract_emails(text)
            emails = ', '.join(email_addresses) if email_addresses else 'N/A'

        return {'study_type': study_type, 'additional_info': additional_info, 'entry_requirements': entry_requirements, 'funds': funds, 'institution_link': institution_link, 'emails': emails}
    except Exception as e:
        print(f"Error extracting additional info from {position_url}: {e}")
        return {'study_type': 'N/A', 'additional_info': 'N/A', 'entry_requirements': 'N/A', 'funds': 'N/A', 'institution_link': 'N/A', 'emails': 'N/A'}

# Function to resolve final URL after redirection
def resolve_redirect_url(url):
    try:
        response = scraper.head(url, allow_redirects=True)
        return response.url
    except Exception as e:
        print(f"Error resolving redirect URL {url}: {e}")
        return url

# Function to extract country name from URL and remove dashes
def extract_country_from_url(url):
    start = url.find('/masters-degrees/') + len('/masters-degrees/')
    end = url.find('/?PG')
    country = url[start:end]
    # Remove dashes from country name
    country = country.replace('-', ' ')
    return country

# Function to clean string
def clean_string(s):
    if not isinstance(s, str):
        return s
    return s.replace('\n', ' ').replace('\r', '').strip()

# Function to send data to API
def savedatatoapi(data):
    url = 'https://jet.aysan.dev/api_v4.php'

    for item in data:
        api_data = {
            'title': clean_string(item.get('title', '')),
            'country': clean_string(item.get('country', '')),
            'university': clean_string(item.get('university', '')),
            'branch': clean_string(item.get('department', '')),
            'overview': clean_string(item.get('overview', '')),
            'funds': item.get('funds'),
            'extra': clean_string(item.get('extra', '')),
            'institution_link': clean_string(item.get('institution_link', '')),
            'level': clean_string(item.get('level', ''))
        }

        # Remove fields with empty values
        api_data = {k: v for k, v in api_data.items() if v not in (None, "")}

        data_to_send = {
            'action': 'insert',
            'data': api_data
        }

        try:
            response = scraper.post(url, json=data_to_send)
            response.raise_for_status()
            result = response.json()
            print(f"Success: {result}")
        except Exception as e:
            print(f"Error sending data to API: {e}")
            print(f"Failed data: {data_to_send}")

# Function to save progress
def save_progress(country, page_num, data):
    progress = {
        'country': country,
        'page_num': page_num,
        'data': data
    }
    with open('progress.json', 'w') as f:
        json.dump(progress, f)

# Function to load progress
def load_progress():
    if os.path.exists('progress.json'):
        with open('progress.json', 'r') as f:
            return json.load(f)
    return None

# Settings
START_PAGE = 1250
END_PAGE = 1300
base_urls = [
    "https://www.findamasters.com/masters-degrees/united-kingdom/?PG=",
    #"https://www.findamasters.com/masters-degrees/germany/?PG=",
    #"https://www.findamasters.com/masters-degrees/canada/?PG=",
    #"https://www.findamasters.com/masters-degrees/australia/?PG=",
    #"https://www.findamasters.com/masters-degrees/japan/?PG=",
    #"https://www.findamasters.com/masters-degrees/france/?PG=",
    #"https://www.findamasters.com/masters-degrees/sweden/?PG=",
    #"https://www.findamasters.com/masters-degrees/usa/?PG="
]

# Load previous progress (if exists)
progress = load_progress()
if progress:
    start_country = progress['country']
    start_page = progress['page_num']
    all_infos = progress['data']
    print(f"Resuming from {start_country}, page {start_page}")
else:
    start_country = None
    start_page = START_PAGE
    all_infos = []

resume = start_country is not None

for base_url in base_urls:
    country_name = extract_country_from_url(base_url)

    if resume and country_name != start_country:
        continue

    for page_num in range(start_page, END_PAGE + 1):
        url = f"{base_url}{page_num}"
        try:
            print(f"Processing {country_name}, page {page_num}")
            infos = extract_info_from_page(url, country_name)
            all_infos.extend(infos)

            # Save progress after each page
            save_progress(country_name, page_num + 1, all_infos)

            # Increase delay between requests
            time.sleep(random.uniform(5, 10))
        except Exception as e:
            print(f"Error processing {url}: {e}")
            # If an error occurs, save progress and wait for 2 minutes
            save_progress(country_name, page_num, all_infos)
            time.sleep(120)
            continue

    # Save progress after finishing each country
    save_progress(country_name, START_PAGE, all_infos)
    start_page = START_PAGE  # Reset to first page for next country
    resume = False  # No need to resume anymore

# Create DataFrame
df = pd.DataFrame(all_infos)

# Send the data to the API
savedatatoapi(all_infos)

# Print DataFrame
print(df)

# Remove progress file after successful completion
if os.path.exists('progress.json'):
    os.remove('progress.json')
