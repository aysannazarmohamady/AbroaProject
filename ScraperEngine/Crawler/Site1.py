import requests
from bs4 import BeautifulSoup
from urllib.parse import urlparse

def fetch_data_by_url(url, start_index):
    response = requests.get(url)
    soup = BeautifulSoup(response.text, 'html.parser')
    
    # Extract country from the URL
    country = extract_country(url)
    
    # Find all div elements with the specific class
    results = soup.find_all("div", class_="resultsRow phd-result-row-standard phd-result row py-2 w-100 px-0 m-0")
    positions_found = 0
    
    for result in results:
        title_tag = result.find("h3")
        if title_tag:
            title_link = title_tag.find("a", class_="h4 text-dark mx-0 mb-3")
            if title_link:
                # Increment the counter and print the position number and title
                start_index += 1
                detail_url = 'https://www.findaphd.com' + title_link.get('href')
                print(f"{start_index}. Title of Position: {title_link.text.strip()}")
                print(f"Country: {country}")
                fetch_details(detail_url)  # Fetch detailed information for each PhD position
                positions_found += 1
    
    return positions_found

def extract_country(url):
    parsed_url = urlparse(url)
    path_parts = parsed_url.path.split('/')
    try:
        # Country is expected to be the second part in the path: /phds/[country]/...
        return path_parts[2]
    except IndexError:
        return "Unknown"

def fetch_details(url):
    response = requests.get(url)
    soup = BeautifulSoup(response.text, 'html.parser')
    
    try:
        university = soup.select_one(".phd-header__institution").text.strip()
        field = soup.select_one(".phd-header__department").text.strip()
        supervisor_info = soup.select_one(".emailLink[data-email-name]").text.strip()
        description_content = soup.select_one("#phd__holder > div > div > div.col-24.px-0.px-md-3.col-md-17 > div.phd-sections.phd-sections__description.row.mx-0.ml-md-n3.mr-md-0.my-3 > div")
        email_phrase = next((s for s in description_content.stripped_strings if '@' in s), None)
        
        print(f"University: {university}")
        print(f"Field: {field}")
        print(f"Supervisor: {supervisor_info}")
        if email_phrase:
            print(f"Email: {email_phrase}")
    except AttributeError as e:
        print("Some information could not be extracted.")
    print("---------------------------------------------------------------\n")

# URL patterns for USA and Canada
urls = [
    "https://www.findaphd.com/phds/usa/?g00l20&PG={}",
    "https://www.findaphd.com/phds/canada/?g00l20&PG={}"
]

# Loop over the URLs and page numbers
start_index = 0
for base_url in urls:
    for j in range(1, 4):  # Loop to change pages or process multiple sets of data
        url = base_url.format(j)
        start_index += fetch_data_by_url(url, start_index)
