import requests
from bs4 import BeautifulSoup
import re
from googlesearch import search
import pandas as pd

def fetch_data_by_url(url, start_index, country):
    paginated_url = url.format(i=start_index)
    response = requests.get(paginated_url)
    response.raise_for_status()

    soup = BeautifulSoup(response.text, 'html.parser')
    li_elements = soup.find_all('li')
    results = []

    for li in li_elements:
        h4_tag = li.find('h4')
        if h4_tag:
            a_tag = h4_tag.find('a')
            if a_tag:
                title = a_tag.get_text(strip=True)
                if 'PhD' in title:
                    link = 'http://scholarshipdb.net' + a_tag['href']
                    result = process_phd_listing(a_tag, link, country)
                    results.append(result)

    return results

def process_phd_listing(a_tag, link, country):
    university_name = extract_university_name_from_link(a_tag) or fetch_university_name_from_page(link)
    branch_department = fetch_branch_department_from_page(link)
    supervisor_name = fetch_supervisor_from_page(link)
    
    # Construct Google search query
    google_search_link = None
    if supervisor_name != "Unknown Supervisor" and university_name != "Unknown University":
        search_query = f"{supervisor_name} {university_name}"
        google_search_link = perform_google_search(search_query)

    return {
        'Title': a_tag.get_text(strip=True),
        'Link': link,
        'Country': country,
        'University': university_name,
        'Branch/Department': branch_department,
        'Supervisor': supervisor_name,
        'Google Search Link': google_search_link,
    }

def extract_university_name_from_link(a_tag):
    university_link = a_tag.get('href')
    if university_link and 'scholarships-at' in university_link:
        return a_tag.text.strip()
    return None

def fetch_university_name_from_page(link):
    response = requests.get(link)
    response.raise_for_status()
    soup = BeautifulSoup(response.text, 'html.parser')
    return extract_university_from_text(soup.get_text())

def extract_university_from_text(text):
    match = re.search(r'University of (\w+)', text)
    return f"University of {match.group(1)}" if match else "Unknown University"

def fetch_branch_department_from_page(link):
    response = requests.get(link)
    response.raise_for_status()
    soup = BeautifulSoup(response.text, 'html.parser')
    return extract_branch_department_from_text(soup.get_text())

def extract_branch_department_from_text(text):
    match = re.search(r'(Department of [\w\s]+|School of [\w\s]+|Faculty of [\w\s]+|Institute of [\w\s]+)', text)
    return match.group(1).strip() if match else "Unknown Branch/Department"

def fetch_supervisor_from_page(link):
    response = requests.get(link)
    response.raise_for_status()
    soup = BeautifulSoup(response.text, 'html.parser')
    return extract_supervisor_from_text(soup.get_text())

def extract_supervisor_from_text(text):
    match = re.search(r'(Supervisor[:\s]*[\w\s]+|Prof\. [\w\s]+|Dr\. [\w\s]+)', text, re.IGNORECASE)
    return match.group(0).strip() if match else "Unknown Supervisor"

def perform_google_search(query):
    search_results = search(query)
    for result in search_results:
        if '.edu' in result:
            return result
    return "No .edu link found"

# URLs and data gathering
urls = [
    {'url': 'http://scholarshipdb.net/PhD-scholarships-in-United-States?page={i}', 'country': 'United States'},
    {'url': 'http://scholarshipdb.net/PhD-scholarships-in-Canada?page={i}', 'country': 'Canada'}
]

all_data = []
for url_info in urls:
    for page_num in range(1, 5):
        data = fetch_data_by_url(url_info['url'], page_num, url_info['country'])
        all_data.extend(data)

# Create a DataFrame
df = pd.DataFrame(all_data)

# Print the DataFrame
print(df)

# Optionally, save the DataFrame to a CSV file
df.to_csv('site2.csv', index=False)
