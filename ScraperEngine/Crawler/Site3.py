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
    
    # متغیر شمارنده برای نمایش شماره هر آگهی
    counter = 1

    for card in job_cards:
        job_link = card.find('a', class_='text-dark text-decoration-none hover-title-underline job-link')
        if job_link:
            job_title = job_link.find('h4')
            full_link = 'https://academicpositions.com' + job_link['href']  # اضافه کردن قسمت ابتدایی لینک
            if job_title:
                print(f"{counter}. Title of Position: {job_title.text.strip()}")
                print(f"   Link: {full_link}")
                print("---------------------------------------------------------------\n")
                counter += 1  # افزایش شمارنده برای آیتم بعدی
else:
    print("Failed to retrieve the page. Status code:", response.status_code)
