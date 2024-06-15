import requests
from bs4 import BeautifulSoup

def fetch_data_by_url(url, start_index):
    # جایگزینی پارامتر صفحه در URL
    paginated_url = url.format(i=start_index)
    
    # درخواست HTTP به وب‌سایت ارسال کنید
    response = requests.get(paginated_url)
    response.raise_for_status()  # چک کردن موفقیت درخواست
    
    # محتوای HTML را با BeautifulSoup پردازش کنید
    soup = BeautifulSoup(response.text, 'html.parser')
    
    # پیدا کردن همه‌ی باکس‌هایی که ساختاری شبیه به توضیحات داده شده دارند
    li_elements = soup.find_all('li')
    
    results = []
    
    # حلقه برای پیمایش و استخراج اطلاعات از هر باکس
    for li in li_elements:
        h4_tag = li.find('h4')
        if h4_tag:
            a_tag = h4_tag.find('a')
            if a_tag:
                title = a_tag.get_text(strip=True)
                if 'PhD' in title:
                    link = 'http://scholarshipdb.net' + a_tag['href']
                    result = {'Title': title, 'Link': link}
                    results.append(result)
    
    return results

# لیست URL های پایه
urls = [
    'http://scholarshipdb.net/PhD-scholarships-in-United-States?page={i}',
    'http://scholarshipdb.net/PhD-scholarships-in-Canada?page={i}'
]

# حلقه برای پیمایش URLها و شماره صفحات
all_data = []
for base_url in urls:
    for page_num in range(1, 5):  # حلقه برای تغییر صفحات یا پردازش مجموعه‌های داده مختلف
        url = base_url.format(i=page_num)
        data = fetch_data_by_url(url, page_num)
        all_data.extend(data)

# نمایش داده‌ها با شماره‌گذاری
for index, item in enumerate(all_data, start=1):
    print(f"{index}. Title of Position: {item['Title']}")
    print(f"   Link: {item['Link']}")
    print('-' * 50)
