import requests
from bs4 import BeautifulSoup
import re

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
                    university_name = extract_university_name_from_link(a_tag)
                    branch_department = fetch_branch_department_from_page(link)
                    supervisor_name = fetch_supervisor_from_page(link)
                    if university_name == "Unknown University":
                        university_name = fetch_university_name_from_page(link)
                    result = {
                        'Title': title,
                        'Link': link,
                        'Country': country,
                        'University': university_name,
                        'Branch/Department': branch_department,
                        'Supervisor': supervisor_name
                    }
                    results.append(result)

    return results

def extract_university_name_from_link(a_tag):
    university_name = "Unknown University"
    university_link = a_tag.get('href')
    if university_link and 'scholarships-at' in university_link:
        soup = BeautifulSoup(str(a_tag), 'html.parser')
        possible_name = soup.find('a').text.strip()
        if possible_name:
            university_name = possible_name
    return university_name

def fetch_university_name_from_page(link):
    try:
        response = requests.get(link)
        response.raise_for_status()
        soup = BeautifulSoup(response.text, 'html.parser')
        text = soup.get_text()
        university_name = extract_university_from_text(text)
    except Exception as e:
        university_name = "Unknown University"
    return university_name

def extract_university_from_text(text):
    match = re.search(r'University of (\w+)', text)
    if match:
        return f"University of {match.group(1)}"
    return "Unknown University"

def fetch_branch_department_from_page(link):
    try:
        response = requests.get(link)
        response.raise_for_status()
        soup = BeautifulSoup(response.text, 'html.parser')
        text = soup.get_text()
        branch_department = extract_branch_department_from_text(text)
    except Exception as e:
        branch_department = "Unknown Branch/Department"
    return branch_department

def extract_branch_department_from_text(text):
    match = re.search(r'(Department of [\w\s]+|School of [\w\s]+|Faculty of [\w\s]+|Institute of [\w\s]+)', text)
    if match:
        return match.group(1).strip()
    return "Unknown Branch/Department"

def fetch_supervisor_from_page(link):
    try:
        response = requests.get(link)
        response.raise_for_status()
        soup = BeautifulSoup(response.text, 'html.parser')
        text = soup.get_text()
        supervisor_name = extract_supervisor_from_text(text)
    except Exception as e:
        supervisor_name = "Unknown Supervisor"
    return supervisor_name

def extract_supervisor_from_text(text):
    match = re.search(r'(Supervisor[:\s]*[\w\s]+|Prof\. [\w\s]+|Dr\. [\w\s]+)', text, re.IGNORECASE)
    if match:
        return match.group(0).strip()
    return "Unknown Supervisor"

urls = [
    {'url': 'http://scholarshipdb.net/PhD-scholarships-in-United-States?page={i}', 'country': 'United States'},
    {'url': 'http://scholarshipdb.net/PhD-scholarships-in-Canada?page={i}', 'country': 'Canada'}
]

all_data = []
for url_info in urls:
    base_url = url_info['url']
    country = url_info['country']
    for page_num in range(1, 5):
        data = fetch_data_by_url(base_url, page_num, country)
        all_data.extend(data)

for index, item in enumerate(all_data, start=1):
    print(f"{index}. Title of Position: {item['Title']}")
    print(f"   Link: {item['Link']}")
    print(f"   Country: {item['Country']}")
    print(f"   University: {item['University']}")
    print(f"   Branch/Department: {item['Branch/Department']}")
    print(f"   Supervisor: {item['Supervisor']}")
    print('-' * 50)
