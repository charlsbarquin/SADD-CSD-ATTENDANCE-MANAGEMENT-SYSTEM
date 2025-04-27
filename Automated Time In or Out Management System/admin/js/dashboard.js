/**
 * Bicol University Polangui - Admin Dashboard JS
 * Handles all interactive elements of the dashboard
 */

// Main initialization when DOM is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Admin Dashboard Initializing...');
    
    // Initialize all components
    initActivityChart();
    setupNotificationSystem();
    initAttendanceModals();
    initViewButtons();
    
    // Debug - confirm initialization
    console.log('Dashboard components initialized');
});

// ======================
// ACTIVITY CHART
// ======================
function initActivityChart() {
    const ctx = document.getElementById('activityChart');
    if (!ctx) {
        console.error('Error: Chart canvas (#activityChart) not found');
        return;
    }

    if (!window.chartData) {
        console.error('Error: chartData not defined');
        return;
    }

    console.log('Initializing chart with:', window.chartData);

    try {
        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'On Time',
                        data: chartData.onTime,
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1,
                        borderRadius: 4
                    },
                    {
                        label: 'Late (After 10 PM)',
                        data: chartData.late,
                        backgroundColor: 'rgba(255, 193, 7, 0.7)',
                        borderColor: 'rgba(255, 193, 7, 1)',
                        borderWidth: 1,
                        borderRadius: 4
                    },
                    {
                        label: 'Absent',
                        data: chartData.absent,
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 1,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false
                        },
                        ticks: {
                            stepSize: 1,
                            precision: 0,
                            padding: 8
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });
        console.log('Activity chart initialized successfully');
    } catch (error) {
        console.error('Chart initialization failed:', error);
    }
}

// ======================
// NOTIFICATION SYSTEM
// ======================
let lastNotificationTimestamp = null;

function setupNotificationSystem() {
    if (!document.querySelector('.notification-list')) {
        console.log('Notification list not found, skipping notification system');
        return;
    }

    // Request notification permission
    if (window.Notification && Notification.permission !== "granted") {
        Notification.requestPermission().then(permission => {
            console.log('Notification permission:', permission);
        });
    }

    // Initial load and then poll every 5 seconds
    fetchNewNotifications();
    setInterval(fetchNewNotifications, 5000);

    // Also check when page becomes visible
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) fetchNewNotifications();
    });
}

function fetchNewNotifications() {
    fetch(`realtime-notifications.php?last_timestamp=${lastNotificationTimestamp}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.notifications && data.notifications.length > 0) {
                updateNotificationsUI(data.notifications);
                lastNotificationTimestamp = data.last_timestamp;
                showDesktopNotifications(data.notifications);
            }
        })
        .catch(error => {
            console.error('Error fetching notifications:', error);
        });
}

function updateNotificationsUI(notifications) {
    const notificationList = document.querySelector('.notification-list');
    if (!notificationList) return;

    // Prepend new notifications
    notifications.reverse().forEach(notif => {
        const notificationItem = document.createElement('a');
        notificationItem.className = 'list-group-item list-group-item-action notification-item';
        notificationItem.href = '#';
        notificationItem.innerHTML = `
            <div class="d-flex justify-content-between align-items-start">
                <div class="notification-content">
                    <span class="notification-text">${notif.action}</span>
                    ${notif.user ? `<small class="text-muted notification-sender">By ${notif.user}</small>` : ''}
                </div>
                <small class="text-muted notification-time">${notif.time_formatted}</small>
            </div>
        `;
        notificationList.prepend(notificationItem);
    });

    // Play sound and trim old notifications
    playNotificationSound();
    trimNotifications(notificationList, 10);
}

function showDesktopNotifications(notifications) {
    if (Notification.permission === "granted") {
        notifications.forEach(notif => {
            new Notification("BUP Attendance System", {
                body: notif.action,
                icon: '../assets/images/bu-logo-small.png'
            });
        });
    }
}

function playNotificationSound() {
    try {
        const audio = new Audio('../assets/sounds/notification.mp3');
        audio.volume = 0.3;
        audio.play().catch(e => console.log('Audio play prevented:', e));
    } catch (e) {
        console.log('Notification sound error:', e);
    }
}

function trimNotifications(list, max) {
    while (list.children.length > max) {
        list.removeChild(list.lastChild);
    }
}

// ======================
// ATTENDANCE MODALS
// ======================
function initAttendanceModals() {
    initBasicAttendanceModals();
    initEnhancedAttendanceModals();
}

function initBasicAttendanceModals() {
    // Use event delegation for dynamically added buttons
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-outline-primary.view-btn')) {
            e.preventDefault();
            const button = e.target.closest('.btn-outline-primary.view-btn');
            const row = button.closest('tr');
            
            if (!row) {
                console.error('Could not find table row');
                return;
            }

            const name = row.cells[0].textContent;
            const designation = row.cells[1].textContent;
            const timeIn = row.cells[2].textContent.trim();
            const timeOut = row.cells[3].textContent.trim();
            const statusBadge = row.cells[4].querySelector('.badge');
            const status = statusBadge ? statusBadge.textContent : 'Unknown';

            showBasicModal(name, designation, timeIn, timeOut, status);
        }
    });
}

function showBasicModal(name, designation, timeIn, timeOut, status) {
    const workDuration = calculateWorkDuration(timeIn, timeOut);
    
    const modalHtml = `
        <div class="modal fade" id="attendanceDetailModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-user-clock me-2"></i>Attendance Details
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-card mb-3">
                                    <h6><i class="fas fa-user-tie me-2"></i>Professor Information</h6>
                                    <div class="info-item">
                                        <span class="info-label">Name:</span>
                                        <span class="info-value">${name}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Designation:</span>
                                        <span class="info-value">${designation}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-card mb-3">
                                    <h6><i class="fas fa-calendar-check me-2"></i>Attendance Details</h6>
                                    <div class="info-item">
                                        <span class="info-label">Time In:</span>
                                        <span class="info-value">${timeIn || '--'}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Time Out:</span>
                                        <span class="info-value">${timeOut || '--'}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Duration:</span>
                                        <span class="info-value">${workDuration}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Status:</span>
                                        <span class="badge ${getStatusClass(status)}">${status}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    showModal(modalHtml);
}

function initEnhancedAttendanceModals() {
    document.addEventListener('click', function(e) {
        if (e.target.closest('.view-attendance-btn')) {
            e.preventDefault();
            const button = e.target.closest('.view-attendance-btn');
            const attendanceId = button.getAttribute('data-id');
            
            showLoadingModal();
            
            fetch(`get-attendance-details.php?id=${attendanceId}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    removeModal();
                    showEnhancedModal(data);
                })
                .catch(error => {
                    console.error('Error fetching attendance details:', error);
                    removeModal();
                    showErrorModal(error);
                });
        }
    });
}

function showEnhancedModal(data) {
    const modalHtml = `
        <div class="modal fade" id="attendanceDetailModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-id-card me-2"></i>Detailed Attendance Record
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-card mb-4">
                                    <h6><i class="fas fa-user-graduate me-2"></i>Professor Information</h6>
                                    <div class="info-item">
                                        <span class="info-label">Name:</span>
                                        <span class="info-value">${data.name || '--'}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Designation:</span>
                                        <span class="info-value">${data.designation || '--'}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Email:</span>
                                        <span class="info-value">${data.email || '--'}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Department:</span>
                                        <span class="info-value">${data.department || '--'}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-card mb-4">
                                    <h6><i class="fas fa-clock me-2"></i>Attendance Details</h6>
                                    <div class="info-item">
                                        <span class="info-label">Date:</span>
                                        <span class="info-value">${data.checkin_date || '--'}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Time In:</span>
                                        <span class="info-value">${data.check_in ? formatTime(data.check_in) : '--'}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Time Out:</span>
                                        <span class="info-value">${data.check_out ? formatTime(data.check_out) : '--'}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Duration:</span>
                                        <span class="info-value">${data.work_duration || '--'}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Status:</span>
                                        <span class="badge ${getStatusClass(data.status)}">${data.status || '--'}</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Notes:</span>
                                        <span class="info-value">${data.notes || 'None'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        ${data.latitude && data.longitude ? createMapSection(data.latitude, data.longitude) : ''}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Close
                        </button>
                        ${!data.check_out ? `
                        <button type="button" class="btn btn-primary" id="forceCheckoutBtn">
                            <i class="fas fa-sign-out-alt me-1"></i>Force Check-Out
                        </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;

    showModal(modalHtml);
    
    // Initialize map if available
    if (data.latitude && data.longitude) {
        initMapIfAvailable(data.latitude, data.longitude);
    }
    
    // Add force checkout handler if button exists
    const forceCheckoutBtn = document.getElementById('forceCheckoutBtn');
    if (forceCheckoutBtn) {
        forceCheckoutBtn.addEventListener('click', function() {
            alert('Force checkout functionality would be implemented here');
            // Implement actual force checkout logic
        });
    }
}

// ======================
// MODAL MANAGEMENT
// ======================
function showLoadingModal() {
    const loadingHtml = `
        <div class="modal fade" id="attendanceDetailModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-body text-center py-5">
                        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <h5 class="mt-3">Loading Attendance Details</h5>
                        <p class="text-muted">Please wait while we fetch the information...</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    showModal(loadingHtml);
}

function showErrorModal(error) {
    const errorHtml = `
        <div class="modal fade" id="attendanceDetailModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle me-2"></i>Error
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <h5><i class="fas fa-times-circle me-2"></i>Failed to load details</h5>
                            <p class="mb-1">We couldn't retrieve the attendance information.</p>
                            <small class="text-muted">${error.message || 'Unknown error occurred'}</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Close
                        </button>
                        <button type="button" class="btn btn-primary" onclick="window.location.reload()">
                            <i class="fas fa-sync-alt me-1"></i>Try Again
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    showModal(errorHtml);
}

function showModal(html) {
    // Remove existing modal if any
    removeModal();
    
    // Add new modal to DOM
    document.body.insertAdjacentHTML('beforeend', html);
    
    // Initialize and show modal
    const modalElement = document.getElementById('attendanceDetailModal');
    if (modalElement) {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
        
        // Set up removal on hide
        modalElement.addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    } else {
        console.error('Could not find modal element after insertion');
    }
}

function removeModal() {
    const modal = document.getElementById('attendanceDetailModal');
    if (modal) modal.remove();
}

// ======================
// UTILITY FUNCTIONS
// ======================
function calculateWorkDuration(timeIn, timeOut) {
    if (!timeIn || !timeOut || timeIn === '--' || timeOut === '--') return '--';
    
    try {
        const start = parseTime(timeIn);
        const end = parseTime(timeOut);
        const startMinutes = start.hours * 60 + start.minutes;
        const endMinutes = end.hours * 60 + end.minutes;

        if (endMinutes > startMinutes) {
            const totalMinutes = endMinutes - startMinutes;
            const hours = Math.floor(totalMinutes / 60);
            const minutes = totalMinutes % 60;
            return `${hours}h ${minutes}m`;
        }
        return '--';
    } catch (e) {
        console.error("Error calculating duration:", e);
        return '--';
    }
}

function parseTime(timeStr) {
    let [time, period] = timeStr.split(' ');
    let [hours, minutes] = time.split(':').map(Number);

    if (period?.toLowerCase() === 'pm' && hours < 12) hours += 12;
    if (period?.toLowerCase() === 'am' && hours === 12) hours = 0;

    return { hours, minutes };
}

function formatTime(dateTimeStr) {
    try {
        const date = new Date(dateTimeStr);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    } catch (e) {
        console.error("Error formatting time:", e);
        return '--';
    }
}

function getStatusClass(status) {
    if (!status) return 'bg-secondary';
    switch(status.toLowerCase()) {
        case 'late': return 'bg-warning text-dark';
        case 'absent': return 'bg-danger';
        default: return 'bg-success';
    }
}

function createMapSection(lat, lng) {
    return `
        <div class="info-card mt-3">
            <h6><i class="fas fa-map-marker-alt me-2"></i>Location</h6>
            <div id="attendanceMap" class="map-container rounded mb-2"></div>
            <a href="https://www.google.com/maps?q=${lat},${lng}" 
               target="_blank" class="btn btn-sm btn-outline-primary w-100">
                <i class="fas fa-external-link-alt me-1"></i>View in Google Maps
            </a>
        </div>
    `;
}

function initMapIfAvailable(lat, lng) {
    const mapContainer = document.getElementById('attendanceMap');
    if (mapContainer) {
        mapContainer.innerHTML = `
            <iframe 
                width="100%" 
                height="300"
                style="border:0"
                loading="lazy"
                allowfullscreen
                referrerpolicy="no-referrer-when-downgrade"
                src="https://www.google.com/maps/embed/v1/view?key=YOUR_API_KEY&center=${lat},${lng}&zoom=15&maptype=roadmap">
            </iframe>
        `;
    }
}

// ======================
// VIEW BUTTONS
// ======================
function initViewButtons() {
    // This is now handled by the modal initialization functions
    console.log('View buttons initialized via modal systems');
}

// ======================
// INITIAL DEBUGGING
// ======================
console.log('Dashboard JS loaded');