import requests
from bs4 import BeautifulSoup

def fetch_job_details(job_url):
    try:
        response = requests.get(job_url)
        response.raise_for_status()  # بررسی وضعیت پاسخ
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
                            value = value.split()[0]  # گرفتن تنها تاریخ و حذف زمان و منطقه زمانی
                        if title == "Location":
                            value = value.split(",")[-1].strip()  # گرفتن تنها کشور
                            title = "Country"
                        if title == "Employer":
                            title = "University"
                        if title in ["University", "Country", "Application deadline"]:
                            details[title] = value
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
                    print("---------------------------------------------------------------\n")
                    counter += 1
        return counter - start_index  # برگرداندن تعداد موقعیت‌هایی که پردازش شدند
    else:
        print(f"Failed to retrieve the page {page_url}. Status code:", response.status_code)
        return 0

# ابتدایی URL برای صفحات مختلف
base_url = 'https://academicpositions.com/jobs/position/phd?sort=recent&page={}'

# اجرای حلقه برای چند صفحه
start_index = 1
for j in range(1, 5):  # مثلاً برای پردازش سه صفحه
    current_url = base_url.format(j)
    start_index += fetch_data_by_url(current_url, start_index)
