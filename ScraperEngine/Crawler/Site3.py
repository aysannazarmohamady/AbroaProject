import requests
from bs4 import BeautifulSoup
import re
import pandas as pd

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
                full_name = match.group() + ' ' + ' '.join(supervisor_name)
                full_name = re.sub(r'[.,;:!?]+$', '', full_name)
                if any(punct in word for word in supervisor_name for punct in [".", ","]):
                    continue
                supervisors.add(full_name)

    return supervisors

def find_nearest_email(name, text):
    pattern = re.compile(r'[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}')
    emails = pattern.findall(text)
    
    nearest_email = None
    min_distance = float('inf')
    
    for email in emails:
        distance = text.find(email) - text.find(name)
        if 0 <= distance < min_distance:
            min_distance = distance
            nearest_email = email
    
    return nearest_email

def fetch_emails(page_text):
    pattern = re.compile(r'[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}')
    emails = pattern.findall(page_text)
    return set(emails)  # استفاده از مجموعه برای حذف ایمیل‌های تکراری

def fetch_logo_url(page_text):
    soup = BeautifulSoup(page_text, 'html.parser')
    logo_div = soup.find('div', class_='employee-avatar-container__header')
    if logo_div:
        img_tag = logo_div.find('img', class_='employee-avatar')
        if img_tag and 'src' in img_tag.attrs:
            return img_tag['src']
    return None

def fetch_fields(page_text):
    soup = BeautifulSoup(page_text, 'html.parser')
    fields_div = soup.find_all('div', class_='row')
    fields = []
    for field_div in fields_div:
        title_div = field_div.find('div', class_='col-12 col-md-4')
        value_div = field_div.find('div', class_='col-auto col-md-8')
        if title_div and value_div:
            title = title_div.get_text(strip=True)
            if 'Field' in title:
                links = value_div.find_all('a', class_='text-dark')
                for link in links:
                    fields.append(link.get_text(strip=True))
    return fields

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
            text = soup.get_text()
            logo_url = fetch_logo_url(response.text)
            fields = fetch_fields(response.text)
            
            if supervisors:
                details["Supervisors"] = []
                details["Emails"] = list(emails)  # تمامی ایمیل‌ها را اضافه کنید
                details["Supervisor Emails"] = []
                for supervisor in supervisors:
                    details["Supervisors"].append(supervisor)
                    email = find_nearest_email(supervisor, text)
                    if email and email not in details["Supervisor Emails"]:
                        details["Supervisor Emails"].append(email)
            if logo_url:
                details["Logo URL"] = logo_url
            if fields:
                details["Fields"] = fields

            return details
    except requests.exceptions.RequestException as e:
        print(f"Failed to retrieve job details from {job_url}: {e}")
    return "Job details not found"

def fetch_data_by_url(page_url, start_index, df):
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
                        row = {
                            "Title of Position": job_title.text.strip(),
                            "Link": full_link
                        }
                        printed_emails = set()  # مجموعه‌ای برای نگهداری ایمیل‌های چاپ شده
                        for key in ["University", "Country", "Application deadline"]:
                            if key in job_details:
                                row[key] = job_details[key]
                                print(f"   {key}: {job_details[key]}")
                        if "Supervisors" in job_details:
                            row["Supervisors"] = ', '.join(job_details["Supervisors"])
                            for supervisor in job_details["Supervisors"]:
                                print(f"   Supervisor: {supervisor}")
                        if "Supervisor Emails" in job_details:
                            row["Supervisor Emails"] = ', '.join(job_details["Supervisor Emails"])
                            for email in job_details["Supervisor Emails"]:
                                if email not in printed_emails:
                                    print(f"   Supervisor Email: {email}")
                                    printed_emails.add(email)
                        if "Emails" in job_details:
                            row["Emails"] = ', '.join(job_details["Emails"])
                            for email in job_details["Emails"]:
                                if email not in printed_emails:
                                    print(f"   Email: {email}")
                                    printed_emails.add(email)
                        if "Logo URL" in job_details:
                            row["Logo URL"] = job_details["Logo URL"]
                            print(f"   Logo URL: {job_details['Logo URL']}")
                        if "Fields" in job_details:
                            row["Fields"] = ', '.join(job_details["Fields"])
                            print(f"   Fields: {', '.join(job_details['Fields'])}")
                        
                        df = pd.concat([df, pd.DataFrame([row])], ignore_index=True)
                    print("---------------------------------------------------------------\n")
                    counter += 1
        return df, counter - start_index
    else:
        print(f"Failed to retrieve the page {page_url}. Status code:", response.status_code)
        return df, 0

# ابتدایی URL برای صفحات مختلف
base_url = 'https://academicpositions.com/jobs/position/phd?sort=recent&page={}'

# اجرای حلقه برای چند صفحه
start_index = 1
results_df = pd.DataFrame(columns=["Title of Position", "Link", "University", "Country", "Application deadline", "Supervisors", "Supervisor Emails", "Emails", "Logo URL", "Fields"])

for j in range(1, 4):  # Example: fetching first 3 pages
    current_url = base_url.format(j)
    results_df, num_results = fetch_data_by_url(current_url, start_index, results_df)
    start_index += num_results

# ذخیره نتایج در یک فایل CSV
results_df.to_csv('job_details.csv', index=False)
print("Data has been saved to job_details.csv")

# Load the contents of the job_details.csv file
job_details_df = pd.read_csv('job_details.csv')

# Display the contents of the DataFrame
print(job_details_df)
