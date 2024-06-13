import requests
from bs4 import BeautifulSoup

def fetch_data_by_url(url, start_index):
    response = requests.get(url)
    soup = BeautifulSoup(response.text, 'html.parser')
    
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
                fetch_details(detail_url)  # Fetch detailed information for each PhD position
                positions_found += 1
    
    return positions_found

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

# URL pattern and the initial start index
start_index = 0
for j in range(1, 4):  # Loop to change pages or process multiple sets of data
    url = f"https://www.findaphd.com/phds/usa/?g00l20&PG={j}"
    start_index += fetch_data_by_url(url, start_index)
