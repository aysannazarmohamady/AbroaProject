import requests
from bs4 import BeautifulSoup

# لینک صفحه وب
url = 'https://academicpositions.com/jobs/position/phd'

# دریافت محتوای صفحه وب
response = requests.get(url)
soup = BeautifulSoup(response.content, 'html.parser')

# پیدا کردن تمام بخش‌هایی که دارای کلاس card-body هستند
positions = soup.find_all('div', class_='card-body')

# استخراج لینک مربوط به هر پوزیشن
for position in positions:
    link = position.find('a', href=True)
    if link:
        print(link['href'])
