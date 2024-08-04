document.addEventListener('DOMContentLoaded', function() {
    fetchDashboardData();
});

function fetchDashboardData() {
    fetch('dashboard_data.php')
        .then(response => response.json())
        .then(data => {
            updateDashboard(data);
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Failed to fetch dashboard data.', 'error');
        });
}

function updateDashboard(data) {
    // Update stats
    document.getElementById('totalUsers').textContent = data.totalUsers;
    document.getElementById('activeProfiles').textContent = data.activeProfiles;
    document.getElementById('newUsers').textContent = data.newUsers || 0;
    document.getElementById('cvUploads').textContent = data.cvUploads;

    // Create charts
    createCountryDistributionChart(data.countryDistribution);
    createFieldOfStudyChart(data.fieldOfStudyDistribution);
    createEducationLevelChart(data.educationLevelDistribution);

    // Update user table
    updateUserTable(data.userInfo);
}

function createCountryDistributionChart(data) {
    const ctx = document.getElementById('countryChart').getContext('2d');
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: Object.keys(data),
            datasets: [{
                data: Object.values(data),
                backgroundColor: [
                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                    '#FF9F40', '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                },
                title: {
                    display: true,
                    text: 'User Distribution by Country'
                }
            }
        }
    });
}

function createFieldOfStudyChart(data) {
    const ctx = document.getElementById('fieldOfStudyChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: Object.keys(data),
            datasets: [{
                label: 'Number of Users',
                data: Object.values(data),
                backgroundColor: 'rgba(54, 162, 235, 0.8)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: 'Distribution by Field of Study'
                }
            }
        }
    });
}

function createEducationLevelChart(data) {
    const ctx = document.getElementById('educationLevelChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(data),
            datasets: [{
                data: Object.values(data),
                backgroundColor: [
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                },
                title: {
                    display: true,
                    text: 'Distribution by Education Level'
                }
            }
        }
    });
}

function updateUserTable(users) {
    const tableBody = document.getElementById('userTableBody');
    tableBody.innerHTML = ''; // Clear existing rows

    Object.entries(users).forEach(([userId, user]) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${user.first_name} ${user.last_name || ''}</td>
            <td>${user.email || ''}</td>
            <td>${user.phone || ''}</td>
            <td>${user.residence || ''}</td>
            <td>${user.language_certificate || ''}</td>
            <td>${user.field || ''}</td>
            <td>${user.country || ''}</td><td>${user.education_level || ''}</td>
            <td>
                <button class="btn-view" data-userid="${userId}">View</button>
                <button class="btn-edit" data-userid="${userId}">Edit</button>
            </td>
        `;
        tableBody.appendChild(row);
    });

    // Add event listeners for view and edit buttons
    document.querySelectorAll('.btn-view').forEach(button => {
        button.addEventListener('click', () => viewUser(button.dataset.userid));
    });
    document.querySelectorAll('.btn-edit').forEach(button => {
        button.addEventListener('click', () => editUser(button.dataset.userid));
    });
}

function viewUser(userId) {
    // Implement view user functionality
    console.log('Viewing user:', userId);
    // You can implement a modal or a new page to show detailed user information
    showNotification(`Viewing user with ID: ${userId}`);
}

function editUser(userId) {
    // Implement edit user functionality
    console.log('Editing user:', userId);
    // You can implement a modal or a new page for editing user information
    showNotification(`Editing user with ID: ${userId}`);
}

function showNotification(message, type = 'info') {
    const notificationArea = document.createElement('div');
    notificationArea.className = 'notification-area';
    document.body.appendChild(notificationArea);

    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;

    const closeBtn = document.createElement('button');
    closeBtn.textContent = '×';
    closeBtn.className = 'close-btn';
    closeBtn.onclick = () => notification.remove();

    notification.appendChild(closeBtn);
    notificationArea.appendChild(notification);

    setTimeout(() => notification.remove(), 5000); // Remove after 5 seconds
}

// Call this function periodically to keep the dashboard updated
setInterval(fetchDashboardData, 3000); // Update every 5 minutes