import requests
from bs4 import BeautifulSoup

# تابعی برای استخراج داده‌ها از هر صفحه
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
                if job_title:
                    full_link = 'https://academicpositions.com' + job_link['href']
                    print(f"{counter}. Title of Position: {job_title.text.strip()}")
                    print(f"   Link: {full_link}")
                    print("---------------------------------------------------------------\n")
                    counter += 1
        return counter - start_index  # برگرداندن تعداد موقعیت‌هایی که پردازش شدند
    else:
        print(f"Failed to retrieve the page {page_url}. Status code:", response.status_code)
        return 0

# ابتدایی URL برای صفحات مختلف
base_url = 'https://academicpositions.com/jobs/position/phd?page={}'

# اجرای حلقه برای چند صفحه
start_index = 1
for j in range(1, 4):  # مثلاً برای پردازش سه صفحه
    current_url = base_url.format(j)
    start_index += fetch_data_by_url(current_url, start_index)
