import requests
from bs4 import BeautifulSoup

def fetch_phd_positions(url):
    # Fetch the main page content
    response = requests.get(url)
    soup = BeautifulSoup(response.text, 'html.parser')

    # Find all PhD positions
    results = soup.findAll('div', class_='resultsRow phd-result-row-standard phd-result row py-2 w-100 px-0 m-0')
    for index, result in enumerate(results, start=1):
        # Fetch the title of each PhD position
        title_element = result.find('a', class_='h4 text-dark mx-0 mb-3')
        if title_element:
            title = title_element.text.strip()
            print(f"{index}. Title: {title}")

        # Follow the link to detail page
        detail_link = result.find('a', class_='btn btn-block btn-success rounded-pill text-white')
        if detail_link:
            detail_url = 'https://www.findaphd.com' + detail_link['href']
            detail_response = requests.get(detail_url)
            detail_soup = BeautifulSoup(detail_response.text, 'html.parser')

            # Extract and print detailed information
            extract_details(detail_soup)

def extract_details(soup):
    # Locate the container of interest
    content_div = soup.find('div', class_='page-content row px-0')
    if content_div:
        key_info = content_div.findAll('span', class_='key-info__content')
        for info in key_info:
            print(info.text.strip())  # Print each piece of key information

        # Print email links
        email_links = content_div.findAll('a', class_='emailLink')
        for email in email_links:
            print(f"Email Name: {email.get('data-email-name', 'N/A')}")
            print(f"Email Address: {email['data-email-addr']}")  # Assuming email address is always present

# URL of the PhD listings
url = 'https://www.findaphd.com/phds/usa/?g00l20'
fetch_phd_positions(url)
