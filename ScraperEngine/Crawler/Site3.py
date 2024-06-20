import requests
from bs4 import BeautifulSoup

# آدرس وب‌سایتی که می‌خواهیم از آن داده‌ها را استخراج کنیم
url = 'https://academicpositions.com/jobs/position/phd?sort=recent'

# درخواست برای دریافت صفحه
response = requests.get(url)

# چک کردن اینکه آیا درخواست موفق بوده است
if response.status_code == 200:
    # استفاده از BeautifulSoup برای تجزیه HTML
    soup = BeautifulSoup(response.text, 'html.parser')

    # پیدا کردن دیو مورد نظر که شامل لیست شغل‌ها است
    job_list = soup.find('div', id='list-jobs')

    # بررسی هر کارت شغلی در داخل دیو مورد نظر
    job_cards = job_list.find_all('div', class_='card shadow-sm mb-4 mb-md-4 job-list-item')
    for card in job_cards:
        job_link = card.find('a', class_='text-dark text-decoration-none hover-title-underline job-link')
        if job_link:
            job_title = job_link.find('h4')
            if job_title:
                print("Job title:", job_title.text.strip())
                print("Job link:", job_link['href'])
else:
    print("Failed to retrieve the page. Status code:", response.status_code)
