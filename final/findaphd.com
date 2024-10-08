import requests
from bs4 import BeautifulSoup
import urllib.parse
import re
import time
import json
from urllib.parse import urlparse, parse_qs

# List of URLs to scrape with a placeholder for page number
urls = [
    #"https://www.findaphd.com/phds/usa/?g00l20&PG={}",
    #"https://www.findaphd.com/phds/canada/?g0w000&PG={}",
    "https://www.findaphd.com/phds/united-kingdom/?g0w900&PG={}",
    "https://www.findaphd.com/phds/germany/?g0Mw00&PG={}",
    "https://www.findaphd.com/phds/australia/?g00w20&PG={}",
    "https://www.findaphd.com/phds/new-zealand/?g0gb30&PG={}",
    "https://www.findaphd.com/phds/france/?g0g920&PG={}",
    "https://www.findaphd.com/phds/ireland/?g00y00&PG={}"
]

def create_google_search_url(supervisor, university):
    query = f"{supervisor} {university}"
    encoded_query = urllib.parse.quote(query)
    return f"https://www.google.com/search?q={encoded_query}"

def extract_country_from_url(url):
    match = re.search(r'phds/([^/?]+)', url)
    if match:
        return match.group(1).replace("-", " ").title()
    return "Unknown"

def scrape_google_results(url):
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
    }
    try:
        response = requests.get(url, headers=headers)
        response.raise_for_status()
        soup = BeautifulSoup(response.content, 'html.parser')

        linkedin_links = []
        researchgate_links = []
        scholar_links = []

        for div in soup.select('#rso > div'):
            link = div.select_one('a')
            if link and link.has_attr('href'):
                href = link['href']
                if 'linkedin.com' in href:
                    linkedin_links.append(href)
                elif 'researchgate.net' in href:
                    researchgate_links.append(href)
                elif 'scholar.google.com' in href:
                    scholar_links.append(href)

        return linkedin_links, researchgate_links, scholar_links
    except requests.RequestException as e:
        print(f"Error scraping Google results: {e}")
        return [], [], []

def extract_real_url(url):
    parsed = urlparse(url)
    if 'clickCount.aspx' in parsed.path:
        query = parse_qs(parsed.query)
        if 'url' in query:
            return query['url'][0]
    return url

def extract_emails(text):
    email_pattern = r'\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b'
    return re.findall(email_pattern, text)

def extract_lines_with_dollar(text):
    lines = text.split('\n')
    return [line.strip() for line in lines if '$' in line]

def insert_researcher(data):
    url = 'https://jet.aysan.dev/api_v4.php'
    payload = {
        'action': 'insert',
        'data': {
            'level': data.get('level'),
            'country': data.get('country'),
            'university': data.get('university'),
            'branch': data.get('branch'),
            'title': data.get('title'),
            'overview': data.get('overview'),
            'supervisors': json.dumps(data.get('supervisors', [])),
            'tags': json.dumps(data.get('tags', [])),
            'email': json.dumps(data.get('email', [])),
            'url': data.get('url'),
            'linkedin_link': json.dumps(data.get('linkedin_link', [])),
            'scholar_link': json.dumps(data.get('scholar_link', [])),
            'researchgate_link': json.dumps(data.get('researchgate_link', [])),
            'google_link': json.dumps(data.get('google_link', [])),
            'institution_link': data.get('institution_link'),
            'funds': json.dumps(data.get('funds', [])),
            'extra': json.dumps(data.get('extra', {}))
        }
    }

    try:
        response = requests.post(url, json=payload)
        print(f"Status Code: {response.status_code}")
        print(f"Response Content: {response.text}")

        response.raise_for_status()  # Raise an exception for bad status codes

        try:
            result = response.json()
            return result
        except json.JSONDecodeError:
            print("Failed to decode JSON. Response might not be in JSON format.")
            return {"error": "Failed to decode JSON response"}
    except requests.RequestException as e:
        print(f"Request failed: {e}")
        return {"error": str(e)}

def scrape_page(url, country, position_counter):
    try:
        response = requests.get(url)
        response.raise_for_status()
        soup = BeautifulSoup(response.content, 'html.parser')
        result_divs = soup.find_all('div', class_='resultsRow phd-result-row-standard phd-result row py-2 w-100 px-0 m-0', id=lambda x: x and x.startswith('searchResultImpression'))

        for div in result_divs:
            position_counter += 1
            researcher_data = {
                'level': 'PhD',
                'country': country,
                'supervisors': [],
                'tags': [],
                'email': [],
                'linkedin_link': [],
                'scholar_link': [],
                'researchgate_link': [],
                'google_link': [],
                'funds': []
            }

            title_element = div.select_one('h3 > a.h4.text-dark.mx-0.mb-3')
            if title_element:
                researcher_data['title'] = title_element.text.strip()

            university_element = div.select_one('div.instDeptRow.phd-result__dept-inst.align-items-center.row.mx-0.mb-3 > a.instLink.col-24.px-0.col-md-auto.phd-result__dept-inst--inst.phd-result__dept-inst--title.h6.mb-0.text-secondary.font-weight-lighter > span')
            if university_element:
                researcher_data['university'] = university_element.text.strip()

            department_element = div.select_one('div.instDeptRow.phd-result__dept-inst.align-items-center.row.mx-0.mb-3 > a.col-24.px-0.col-md-auto.deptLink.phd-result__dept-inst--dept.phd-result__dept-inst--title.h6.mb-0.text-secondary.font-weight-lighter')
            if department_element:
                researcher_data['branch'] = department_element.text.strip()

            overview_element = div.select_one('div.desc.phd-result__description.row.mb-3.mx-0')
            if overview_element:
                overview_text = overview_element.text.replace("Read more", "").strip()
                researcher_data['overview'] = overview_text
                researcher_data['email'].extend(extract_emails(overview_text))
                researcher_data['funds'].extend(extract_lines_with_dollar(overview_text))

            supervisor_element = div.select_one('div.phd-result__key-info--outer.row.p-0.mx-n1')
            if supervisor_element:
                supervisor_text = supervisor_element.text.strip()
                researcher_data['email'].extend(extract_emails(supervisor_text))

                if supervisor_text.startswith("supervisors:"):
                    supervisor_text = supervisor_text[11:].strip()

                supervisors = [supervisor.strip() for supervisor in supervisor_text.split(",")]
                researcher_data['supervisors'] = supervisors

                for supervisor in supervisors:
                    if supervisor and researcher_data.get('university'):
                        google_search_url = create_google_search_url(supervisor, researcher_data['university'])
                        researcher_data['google_link'].append(google_search_url)
                        linkedin_links, researchgate_links, scholar_links = scrape_google_results(google_search_url)
                        researcher_data['linkedin_link'].extend(linkedin_links)
                        researcher_data['researchgate_link'].extend(researchgate_links)
                        researcher_data['scholar_link'].extend(scholar_links)
                        time.sleep(2)

            link_element = div.select_one('div.col-md-6.col-lg-5.col-24.order-md-2.p-0.d-flex.align-items-start.flex-column.justify-content-end > div > a')
            if link_element and link_element.has_attr('href'):
                link_url = "https://www.findaphd.com" + link_element['href']
                researcher_data['url'] = link_url

                try:
                    institution_response = requests.get(link_url)
                    institution_response.raise_for_status()
                    institution_soup = BeautifulSoup(institution_response.content, 'html.parser')

                    institution_link = institution_soup.select_one('#phd__holder > div > div > div.col-24.col-sm-7.tight > div > div > div.phd-sidebar__inner > div.phd-sidebar__buttons.px-4.px-md-0.pb-3 > a.phd-sidebar__button.phd-sidebar__button--main.btn.btn-block.btn-outline-success.rounded-pill')
                    if institution_link and institution_link.has_attr('href'):
                        institution_url = institution_link['href']
                        researcher_data['institution_link'] = extract_real_url(institution_url)

                    additional_info = institution_soup.select('#phd__holder > div > div > div.col-24.px-0.px-md-3.col-md-17 > div.phd-data__container.mx-0.ml-md-n3.row.mr-md-0.px-0 > a')[2:]
                    researcher_data['tags'] = [info.text.strip() for info in additional_info]

                    full_content = institution_soup.get_text()
                    researcher_data['email'].extend(extract_emails(full_content))
                    researcher_data['funds'].extend(extract_lines_with_dollar(full_content))
                except requests.RequestException as e:
                    print(f"Error fetching institution page: {e}")

            # Remove duplicates
            researcher_data['email'] = list(set(researcher_data['email']))
            researcher_data['funds'] = list(set(researcher_data['funds']))

            # Insert data into database
            result = insert_researcher(researcher_data)
            print(f"Inserted researcher {position_counter}: {result}")

        return position_counter
    except requests.RequestException as e:
        print(f"Error scraping page {url}: {e}")
        return position_counter

# Main execution
def main():
    position_counter = 0
    for url_template in urls:
        country = extract_country_from_url(url_template)
        for i in range(1, 30):  # Scraping the first 4 pages
            url = url_template.format(i)
            print(f"Scraping URL: {url}")
            position_counter = scrape_page(url, country, position_counter)
            time.sleep(2)  # Adding a delay between requests to avoid overloading the server

if __name__ == "__main__":
    main()
