import requests
from bs4 import BeautifulSoup
import re

def fetch_supervisor_details(page_text):
    soup = BeautifulSoup(page_text, 'html.parser')
    text = soup.get_text()
    
    supervisors = set()
    patterns = [r'Prof\. dr\. ir\.', r'Prof\. dr\.', r'Prof\.', r'Dr\.', r'Prof\. dr']

    for pattern in patterns:
        matches = re.finditer(pattern, text)
        for match in matches:
            start = match.end()
            next_part = text[start:].strip()
            words = next_part.split()
            
            supervisor_name = []
            for word in words:
                if re.match(r'[.,;:!?]', word) or word == "at":
                    break
                supervisor_name.append(word)
                if len(supervisor_name) >= 2:
                    break
            
            if supervisor_name:
                # حذف علائم نگارشی از انتهای نام
                full_name = match.group() + ' ' + ' '.join(supervisor_name)
                full_name = re.sub(r'[.,;:!?]+$', '', full_name)
                # چک کردن وجود علامت . یا , در دو کلمه‌ی اسم
                if any(punct in word for word in supervisor_name for punct in [".", ","]):
                    continue
                supervisors.add(full_name)

    return supervisors

def fetch_emails(page_text):
    soup = BeautifulSoup(page_text, 'html.parser')
    text = soup.get_text()
    emails = set(re.findall(r'[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}', text))
    return emails

def fetch_job_details(job_url):
    try:
        response = requests.get(job_url)
        response.raise_for_status()
        soup = BeautifulSoup(response.text, 'html.parser')
        job_details = soup.find('div', class_='card card-border border-primary shadow-sm mb-6 mb-md-6', id='jobDetails')
        if job_details:
            details = {}
            body = job_details.find('div', class_='card-body')
            if body:
                rows = body.find_all('div', class_='row mb-3')
                for row in rows:
                    title_col = row.find('div', class_='col-12 col-md-4')
                    value_col = row.find('div', class_='col-auto col-md-8')
                    if title_col and value_col:
                        title = title_col.text.strip()
                        value = value_col.text.strip()
                        if title == "Application deadline":
                            value = value.split()[0]
                        if title == "Location":
                            value = value.split(",")[-1].strip()
                            title = "Country"
                        if title == "Employer":
                            title = "University"
                        if title in ["University", "Country", "Application deadline"]:
                            details[title] = value
            
            supervisors = fetch_supervisor_details(response.text)
            emails = fetch_emails(response.text)
            
            if supervisors:
                details["Supervisors"] = list(supervisors)
            if emails:
                details["Emails"] = list(emails)

            return details
    except requests.exceptions.RequestException as e:
        print(f"Failed to retrieve job details from {job_url}: {e}")
    return "Job details not found"

def fetch_data_by_url(page_url, start_index):
    response = requests.get(page_url)
    if response.status_code == 200:
        soup = BeautifulSoup(response.text, 'html.parser')
        job_list = soup.find('div', id='list-jobs')
        job_cards = job_list.find_all('div', class_='card shadow-sm mb-4 mb-md-4 job-list-item')
        counter = start_index

        for card in job_cards:
            job_link = card.find('a', class_='text-dark text-decoration-none hover-title-underline job-link')
            if job_link:
                job_title = job_link.find('h4')
                full_link = 'https://academicpositions.com' + job_link['href']
                if job_title:
                    print(f"{counter}. Title of Position: {job_title.text.strip()}")
                    print(f"   Link: {full_link}")
                    job_details = fetch_job_details(full_link)
                    if isinstance(job_details, dict):
                        for key in ["University", "Country", "Application deadline"]:
                            if key in job_details:
                                print(f"   {key}: {job_details[key]}")
                        if "Supervisors" in job_details:
                            for supervisor in job_details["Supervisors"]:
                                print(f"   Supervisor: {supervisor}")
                        if "Emails" in job_details:
                            for email in job_details["Emails"]:
                                print(f"   Email: {email}")
                    print("---------------------------------------------------------------\n")
                    counter += 1
        return counter - start_index
    else:
        print(f"Failed to retrieve the page {page_url}. Status code:", response.status_code)
        return 0

# ابتدایی URL برای صفحات مختلف
base_url = 'https://academicpositions.com/jobs/position/phd?sort=recent&page={}'

# اجرای حلقه برای چند صفحه
start_index = 1
for j in range(1, 5):
    current_url = base_url.format(j)
    start_index += fetch_data_by_url(current_url, start_index)
