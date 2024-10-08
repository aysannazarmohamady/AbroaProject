import requests
from bs4 import BeautifulSoup
import re
import time
import pandas as pd
import json
import urllib.parse
import sys

# Initial settings
BASE_URL = 'https://www.academictransfer.com/en/jobs/?vacancy_type=scientific&q=&function_types=1&order=&page='
START_PAGE = 1
END_PAGE = 354  # Number of pages to scrape
HEADERS = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
}

def create_google_search_link(supervisor, university):
    query = f"{supervisor} {university}".replace(" ", "+")
    return f"https://www.google.com/search?q={query}"

def extract_links(google_search_link, domain):
    response = requests.get(google_search_link, headers=HEADERS)
    soup = BeautifulSoup(response.text, 'html.parser')
    links = []
    for div in soup.find_all('div', class_='MjjYud'):
        a_tag = div.find('a', jsname='UWckNb')
        if a_tag and 'href' in a_tag.attrs and domain in a_tag['href']:
            links.append(a_tag['href'])
    return links

def savedatatoapi(item):
  url = 'https://jet.aysan.dev/api_v4.php'


  # Extract the first (and only) item from the list
  #item = data[0]


  api_data = {
        'title': clean_string(item.get('title', '')),
        'country': clean_string(item.get('country', '')),
        'university': clean_string(item.get('university', '')),
        #'url': urllib.parse.quote(clean_string(item.get('url', ''))),
        'funds': item.get('funds'),
        'level': clean_string(item.get('level', '')),
        'extra': clean_string(item.get('extra', '')),
        'overview': clean_string(item.get('overview', '')),
        'email': clean_string(item.get('email', '')),
        'supervisors': clean_string(item.get('supervisor', '')),
        'linkedin_link': clean_string(item.get('linkedin_link', '')),
        'scholar_link': clean_string(item.get('scholar_link', '')),
        'researchgate_link': clean_string(item.get('researchgate_link', '')),
        'google_link': clean_string(item.get('google_link', '')),
        'institution_link': clean_string(item.get('institution_link', ''))
    }
    # حذف فیلدهایی که مقدار خالی دارند
  api_data = {k: v for k, v in api_data.items() if v not in (None, "")}

  data = {
    'action': 'insert',
    'data': api_data
  }
  print(data)
  response = requests.post(url, json=data)
  result = response.json()

  return(result)

def clean_string(s):
    if not isinstance(s, str):
        return s
    return s.replace('\n', ' ').replace('\r', '').strip()

def extract_job_details(job_url):
    job_response = requests.get(job_url)
    job_soup = BeautifulSoup(job_response.text, 'html.parser')

    funds = ""
    deadline = ""
    overview = ""
    emails = []
    institution_links = []
    supervisors = []
    additional_info = []

    # Extract funds
    specs_div = job_soup.find('div', class_='_Specs_Specs_IKUE-UWA')
    if specs_div:
        li_funds_tag = specs_div.find('li', class_='_Specs_salary_X-hy9Mqe')
        if li_funds_tag:
            span_tag_funds = li_funds_tag.find('span')
            if span_tag_funds:
                funds = span_tag_funds.text.strip()

    # Extract deadline
    meta_div = job_soup.find('div', class_='_Meta_meta_OzgOmmvA')
    if meta_div:
        td_deadline = meta_div.find('td', {'data-label': 'Deadline'})
        if td_deadline:
            deadline = td_deadline.text.strip()

    # Extract overview
    section_body_div = job_soup.find('div', class_='_Section_body_uVSjrQzu')
    if section_body_div:
        overview = section_body_div.text.strip().split("\n")[0]

    # Extract additional information from all sections
    section_divs = job_soup.find_all('div', class_='_Section_Section_a9UlTldI')
    for section_div in section_divs:
        text = section_div.get_text()

        # Extract emails and institution links
        for a_tag in section_div.find_all('a', href=True):
            href = a_tag['href']
            if href.startswith('mailto:'):
                emails.append(href.replace('mailto:', ''))
            elif href.startswith('https://') and 'maps.google.com' not in href and 'academictransfer.com' not in href:
                institution_links.append(href)

        # Extract supervisors
        supervisors.extend(re.findall(r'(Dr\.|dr\.|Prof\.|Professor)\s([A-Z][a-z]+(?:\s[A-Z][a-z]+)+)', text))

        # Extract emails from text
        emails.extend(re.findall(r'\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b', text))

        # Add additional information
        #sentences = re.split(r'(?<=[.!?])\s+', text)
        #additional_info.extend([s.strip() for s in sentences if 'contact' in s.lower() or '@' in s or any(title in s for title in ['Dr.', 'dr.', 'Prof.', 'Professor'])])
        # اضافه کردن کل متن به additional_info
        additional_info.append(text)

    # Remove duplicates
    emails = list(set(emails))
    institution_links = list(set(institution_links))
    supervisors = list(set([s[1] for s in supervisors]))
    additional_info = list(set(additional_info))

    return funds, deadline, overview, emails, institution_links, supervisors, additional_info

# List to store job data
data = []

for page in range(START_PAGE, END_PAGE + 1):
    url = f"{BASE_URL}{page}"
    response = requests.get(url)
    soup = BeautifulSoup(response.text, 'html.parser')

    for div in soup.find_all('div', class_='_Vacancy_Vacancy_7P-yunLb'):
        h6_tag = div.find('h6')
        if not h6_tag:
            continue

        span_tag = h6_tag.find('span')
        if not span_tag:
            continue

        university_div = div.find('div', class_='_Employer_Employer_YBmukMHl')
        if not university_div:
            continue

        university = university_div.text.strip()
        a_tag = div.find('a')
        if not a_tag or 'href' not in a_tag.attrs:
            continue

        job_url = 'https://www.academictransfer.com' + a_tag['href']

        funds, deadline, overview, emails, institution_links, supervisors, additional_info = extract_job_details(job_url)

        extra_info = []
        if deadline:
            extra_info.append(f"Deadline: {deadline}")
        extra_info.extend(additional_info)

        job_data = {
            'title': span_tag.text,
            'country': 'Netherlands',
            'university': university,
            'url': job_url,
            'funds': funds or None,
            'level': "PhD",
            'extra': '; '.join(extra_info) if extra_info else None,
            'overview': overview or None,
            'email': ', '.join(emails) if emails else None,
            'institution_link': ', '.join(institution_links) if institution_links else None,
            'supervisor': ', '.join(supervisors) if supervisors else None,
            'google_link': None,
            'researchgate_link': None,
            'scholar_link': None,
            'linkedin_link': None
        }

        if supervisors:
            google_links = []
            researchgate_links = []
            scholar_links = []
            linkedin_links = []
            for supervisor in supervisors:
                google_search_link = create_google_search_link(supervisor, university)
                google_links.append(google_search_link)

                researchgate_links.extend(extract_links(google_search_link, 'https://www.researchgate.net/'))
                scholar_links.extend(extract_links(google_search_link, 'https://scholar.google.com/'))
                linkedin_links.extend(extract_links(google_search_link, 'https://www.linkedin.com/'))

            job_data['google_link'] = ', '.join(google_links)
            job_data['researchgate_link'] = ', '.join(researchgate_links) if researchgate_links else None
            job_data['scholar_link'] = ', '.join(scholar_links) if scholar_links else None
            job_data['linkedin_link'] = ', '.join(linkedin_links) if linkedin_links else None

        #data.append(job_data)

        result = savedatatoapi(job_data)

        print(result)

        time.sleep(2)  # Delay to prevent too frequent requests

print("Data insertion complete.")
