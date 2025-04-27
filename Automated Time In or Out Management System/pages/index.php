<?php
require_once __DIR__ . '/../includes/session.php';
include '../config/database.php';

// Check if professor is logged in
$professorName = '';
$professorId = null;
if (isset($_SESSION['professor_id'])) {
    $professorId = $_SESSION['professor_id'];
    $stmt = $conn->prepare("SELECT name FROM professors WHERE id = ?");
    $stmt->bind_param("i", $professorId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $professor = $result->fetch_assoc();
        $professorName = $professor['name'];
    }
    $stmt->close();
}

// Determine current session
$currentHour = date('H');
$isAM = ($currentHour < 12);
$currentSession = $isAM ? 'AM' : 'PM';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Automated Time In/Out</title>

    <!-- Bootstrap & Custom Styles -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- WebcamJS & jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/webcamjs/1.0.26/webcam.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>

    <?php include '../includes/navbar.php'; ?>

    <!-- Time In Modal -->
    <div class="modal fade" id="timeInModal" tabindex="-1" aria-labelledby="timeInModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header text-white" style="background-color: #0077b6;">
                    <h5 class="modal-title"><i class="fas fa-sign-in-alt me-2"></i> <span id="timeInTitle"><?php echo $currentSession; ?> Time In</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($professorName)): ?>
                        <div class="professor-info mb-4 text-center">
                            <h4>Professor:</h4>
                            <h3 class="fw-bold"><?php echo htmlspecialchars($professorName); ?></h3>
                            <h5 class="text-muted"><?php echo $currentSession; ?> Session</h5>
                        </div>
                    <?php endif; ?>

                    <!-- Camera Section -->
                    <div id="camera-section" class="mt-3">
                        <div class="camera-header d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0"><i class="fas fa-camera me-2"></i>Face Capture</h6>
                        </div>
                        <div class="d-flex justify-content-center">
                            <div id="camera" style="width: 320px; height: 240px;"></div>
                        </div>
                        <button id="take-photo" class="btn btn-primary w-100 mt-2">
                            <i class="fas fa-camera me-2"></i> Capture
                        </button>
                    </div>

                    <!-- Location Status -->
                    <div id="location-status" class="mt-2 small text-center">
                        <i class="fas fa-map-marker-alt me-2"></i> Location services will be used for Timed-In
                    </div>
                    <div id="location-process" class="text-center mt-3 d-none">
                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                        <span>Getting Location...</span>
                        <small id="location-text" class="d-block mt-1"></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Time Out Modal -->
    <div class="modal fade" id="timeOutModal" tabindex="-1" aria-labelledby="timeOutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header text-white" style="background-color: #FF6600;">
                    <h5 class="modal-title"><i class="fas fa-sign-out-alt me-2"></i> <span id="timeOutTitle"><?php echo $currentSession; ?> Time Out</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($professorName)): ?>
                        <div class="professor-info mb-4 text-center">
                            <h4>Professor:</h4>
                            <h3 class="fw-bold"><?php echo htmlspecialchars($professorName); ?></h3>
                            <h5 class="text-muted"><?php echo $currentSession; ?> Session</h5>
                        </div>

                        <div class="confirmation-message text-center mb-3">
                            <p>Are you sure you want to time out from the <?php echo $currentSession; ?> session?</p>
                        </div>

                        <div class="d-grid gap-2">
                            <button id="confirm-timeout" class="btn btn-danger">
                                <i class="fas fa-sign-out-alt me-2"></i> Confirm Time Out
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true" data-refresh-delay="3000">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                    </div>
                    <h3 class="mb-3" id="success-message">Success!</h3>
                    <p id="success-details" class="mb-0"></p>
                    <button class="btn btn-success mt-3" id="success-ok-btn">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <div class="mb-3">
                        <i class="fas fa-times-circle text-danger" style="font-size: 5rem;"></i>
                    </div>
                    <h3 class="mb-3" id="error-title">Error</h3>
                    <p id="error-message" class="mb-0"></p>
                    <button class="btn btn-danger mt-3" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Welcome Message -->
    <?php if (!empty($professorName)): ?>
        <div class="welcome-message">
            <i class="fas fa-user-tie"></i> Welcome, <?php echo htmlspecialchars($professorName); ?>
            <span class="session-badge"><?php echo $currentSession; ?> Session</span>
        </div>
    <?php endif; ?>

    <!-- Sidebar Recent History -->
    <aside class="history-panel">
        <div class="history-header d-flex justify-content-between align-items-center p-3 text-white" style="background-color: #0077b6;">
            <h5 class="mb-0"><i class="fas fa-clock"></i> Recent History</h5>
        </div>
        <div class="history-content">
            <ul id="recent-history-list" class="list-group"></ul>
        </div>
        <button id="view-more-btn" class="btn btn-outline-secondary w-100 rounded-0">
            <span id="view-more-text">View More</span>
            <span id="refresh-spinner" class="spinner-border spinner-border-sm ms-2 d-none" role="status"></span>
        </button>
    </aside>

    <!-- Main Dashboard -->
    <main class="dashboard" id="landing-page">
        <div class="date-container">
            <i class="fas fa-calendar-day"></i> <span id="current-date"></span>
            <span class="session-indicator"><?php echo $currentSession; ?> Session</span>
        </div>

        <div class="clock-container text-center">
            <h1 id="clock" class="fw-bold"></h1>
            <p class="text-muted">Current Time</p>
        </div>

        <div class="button-container text-center mt-4">
            <button id="time-in-btn" class="btn btn-lg text-white time-action-btn" style="background-color: #0099CC; border: none;" data-bs-toggle="modal" data-bs-target="#timeInModal">
                <i class="fas fa-sign-in-alt"></i> <?php echo $currentSession; ?> Time In
            </button>
            <button id="time-out-btn" class="btn btn-lg text-white time-action-btn" style="background-color: #FF6600; border: none;" data-bs-toggle="modal" data-bs-target="#timeOutModal">
                <i class="fas fa-sign-out-alt"></i> <?php echo $currentSession; ?> Time Out
            </button>
        </div>

        <hr class="dashboard-divider">

        <!-- Attendance Statistics -->
        <section class="stats-section mt-4">
            <h3 class="fw-bold text-center"><i class="fas fa-chart-bar"></i> Attendance Overview</h3>
            <div class="stats-container d-flex justify-content-center flex-wrap mt-3">
                <div class="stat-card total-professors">
                    <h4><i class="fas fa-user-tie"></i> Total Professors</h4>
                    <h2>
                        <?php
                        $result = $conn->query("SELECT COUNT(*) AS total FROM professors WHERE status = 'active'");
                        echo $result->fetch_assoc()['total'];
                        ?>
                    </h2>
                </div>

                <div class="stat-card total-attendance">
                    <h4><i class="fas fa-user-check"></i> Today's Attendance</h4>
                    <h2>
                        <?php
                        $result = $conn->query("SELECT COUNT(DISTINCT professor_id) AS total FROM attendance WHERE checkin_date = CURDATE()");
                        echo $result->fetch_assoc()['total'];
                        ?>
                    </h2>
                </div>

                <div class="stat-card pending-checkouts">
                    <h4><i class="fas fa-clock"></i> Pending Time-Outs</h4>
                    <h2>
                        <?php
                        $result = $conn->query("SELECT COUNT(*) AS total FROM attendance WHERE checkin_date = CURDATE() AND 
                                              ((am_check_in IS NOT NULL AND am_check_out IS NULL) OR 
                                               (pm_check_in IS NOT NULL AND pm_check_out IS NULL))");
                        echo $result->fetch_assoc()['total'];
                        ?>
                    </h2>
                </div>
            </div>
        </section>
    </main>

    <!-- JavaScript at the bottom of the page -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/index.js"></script>

    <script>
        // Global variables
        const currentSession = '<?php echo $currentSession; ?>';
        const professorId = <?php echo json_encode($professorId); ?>;
        const professorName = <?php echo json_encode($professorName); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize modals
            const timeInModal = new bootstrap.Modal(document.getElementById('timeInModal'));
            const timeOutModal = new bootstrap.Modal(document.getElementById('timeOutModal'));
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));

            // Initialize Webcam when timeInModal is shown
            document.getElementById('timeInModal').addEventListener('shown.bs.modal', function() {
                Webcam.set({
                    width: 320,
                    height: 240,
                    image_format: 'jpeg',
                    jpeg_quality: 90,
                    constraints: {
                        facingMode: 'user'
                    }
                });
                Webcam.attach('#camera');
            });

            // Reset Webcam when modal hides
            document.getElementById('timeInModal').addEventListener('hidden.bs.modal', function() {
                Webcam.reset();
                resetPhotoButton();
            });

            // Time In Photo Capture
            document.getElementById('take-photo')?.addEventListener('click', handleTimeIn);

            // Time Out Confirmation
            document.getElementById('confirm-timeout')?.addEventListener('click', handleTimeOut);

            // View More button click handler
            document.getElementById('view-more-btn')?.addEventListener('click', function(e) {
                e.preventDefault();
                handleViewMore();
            });

            // Initialize clock and date
            initClockAndDate();

            // Load recent history
            loadRecentHistory();

            // Success modal OK button
            document.getElementById('success-ok-btn')?.addEventListener('click', function() {
                successModal.hide();
                setTimeout(() => location.reload(), 300);
            });

            // Initialize dropdowns
            initDropdowns();
        });

        // ========== CORE FUNCTIONS ========== //

        function initClockAndDate() {
            function update() {
                const now = new Date();

                // Update clock
                document.getElementById('clock').textContent = now.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                });

                // Update date
                document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            }
            update();
            setInterval(update, 1000);
        }

        async function loadRecentHistory() {
            try {
                const response = await fetch('../api/get-recent-history.php');
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                const data = await response.json();
                const historyList = document.getElementById('recent-history-list');
                historyList.innerHTML = ''; // Clear existing content

                if (data && Array.isArray(data)) {
                    if (data.length > 0) {
                        data.forEach(item => {
                            if (!item || typeof item !== 'object') return;

                            const professorName = item.professor_name || 'Unknown Professor';
                            const sessionType = item.session_type || 'Unknown Session';
                            const action = item.action || 'Unknown Action';

                            const time = item.timestamp ? formatTime(item.timestamp) :
                                (item.time ? formatTime(item.time) : '');
                            const date = item.timestamp ? formatDate(item.timestamp) :
                                (item.date ? formatDate(item.date) : '');

                            if (professorName || sessionType || action) {
                                const li = document.createElement('li');
                                li.className = 'list-group-item';
                                li.innerHTML = `
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong>${professorName}</strong>
                                        <div class="text-muted small">
                                            ${sessionType} - ${action}
                                        </div>
                                    </div>
                                    ${time || date ? `
                                    <div class="text-end">
                                        ${time ? `<small class="text-muted">${time}</small>` : ''}
                                        ${date ? `<br><small class="text-muted">${date}</small>` : ''}
                                    </div>
                                    ` : ''}
                                </div>
                            `;
                                historyList.appendChild(li);
                            }
                        });

                        if (historyList.children.length === 0) {
                            historyList.innerHTML = '<li class="list-group-item text-center text-muted">No valid records found</li>';
                        }
                    } else {
                        historyList.innerHTML = '<li class="list-group-item text-center text-muted">No records found</li>';
                    }
                } else {
                    throw new Error('Invalid data format received from server');
                }
            } catch (error) {
                console.error('Error loading history:', error);
                const historyList = document.getElementById('recent-history-list');
                historyList.innerHTML = '<li class="list-group-item text-center text-muted">Error loading history</li>';
            }
        }

        async function handleViewMore() {
            const btn = document.getElementById('view-more-btn');
            const spinner = document.getElementById('refresh-spinner');
            const viewMoreText = document.getElementById('view-more-text');

            try {
                btn.disabled = true;
                viewMoreText.textContent = 'Loading...';
                spinner.classList.remove('d-none');

                await loadRecentHistory();
            } catch (error) {
                console.error('Error loading more history:', error);
            } finally {
                btn.disabled = false;
                viewMoreText.textContent = 'View More';
                spinner.classList.add('d-none');
            }
        }

        async function handleTimeIn() {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing';

            try {
                const position = await new Promise((resolve, reject) => {
                    const locationProcess = document.getElementById('location-process');
                    const locationText = document.getElementById('location-text');

                    if (locationProcess && locationText) {
                        locationProcess.classList.remove('d-none');
                        locationText.textContent = "Getting your location...";
                    }

                    navigator.geolocation.getCurrentPosition(resolve, reject, {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    });
                });

                const {
                    latitude,
                    longitude
                } = position.coords;
                const dataUri = await new Promise((resolve) => Webcam.snap(resolve));

                const response = await fetch('../api/time-in.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `professor_id=${professorId}&image_data=${encodeURIComponent(dataUri)}&latitude=${latitude}&longitude=${longitude}`
                });

                const data = await response.json();

                if (data.status === 'success') {
                    showSuccessModal(
                        `${currentSession} Time In Successful`,
                        `${professorName} has been successfully checked in for ${currentSession} session at ${new Date().toLocaleTimeString()}.`
                    );
                    setTimeout(() => {
                        const timeInModal = bootstrap.Modal.getInstance(document.getElementById('timeInModal'));
                        if (timeInModal) timeInModal.hide();
                    }, 2000);
                } else {
                    throw new Error(data.message || 'Time in failed');
                }
            } catch (error) {
                showErrorModal(
                    'Time In Failed',
                    error.message.includes("NetworkError") ?
                    "Failed to connect to server. Please check your internet connection." :
                    error.message
                );
            } finally {
                resetPhotoButton(btn);
            }
        }

        async function handleTimeOut() {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing';

            try {
                const response = await fetch('../api/time-out.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `professor_id=${professorId}`
                });

                const data = await response.json();

                if (data.status === 'success') {
                    showSuccessModal(
                        `${currentSession} Time Out Successful`,
                        `You have been successfully checked out from ${currentSession} session at ${new Date().toLocaleTimeString()}.`
                    );
                    setTimeout(() => {
                        const timeOutModal = bootstrap.Modal.getInstance(document.getElementById('timeOutModal'));
                        if (timeOutModal) timeOutModal.hide();
                    }, 2000);
                } else {
                    throw new Error(data.message || 'Time out failed');
                }
            } catch (error) {
                showErrorModal('Time Out Failed', error.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sign-out-alt me-2"></i> Confirm Time Out';
            }
        }

        function initDropdowns() {
            // Initialize dropdowns with proper configuration
            const notificationToggle = document.getElementById('notificationDropdown');
            const profileToggle = document.getElementById('profileDropdown');

            if (!notificationToggle || !profileToggle) return;

            // Initialize dropdown instances
            const notificationDropdown = new bootstrap.Dropdown(notificationToggle, {
                autoClose: true,
                boundary: 'viewport' // Prevents dropdown from being cut off
            });

            const profileDropdown = new bootstrap.Dropdown(profileToggle, {
                autoClose: true,
                boundary: 'viewport'
            });

            // Enhanced click handler for notification dropdown
            notificationToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // Close profile dropdown if open
                profileDropdown.hide();

                // Toggle notification dropdown
                notificationDropdown.toggle();
            });

            // Enhanced click handler for profile dropdown
            profileToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // Close notification dropdown if open
                notificationDropdown.hide();

                // Toggle profile dropdown
                profileDropdown.toggle();
            });

            // Close dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                    if (!notificationToggle.contains(e.target)) {
                            notificationDropdown.hide();
                        }
                        if (!profileToggle.contains(e.target)) {
                            profileDropdown.hide();
                        }
                    });

                // Close dropdowns when scrolling
                window.addEventListener('scroll', function() {
                    notificationDropdown.hide();
                    profileDropdown.hide();
                });

                // Close dropdowns when clicking on items
                document.querySelectorAll('.dropdown-menu a').forEach(item => {
                    item.addEventListener('click', function() {
                        notificationDropdown.hide();
                        profileDropdown.hide();
                    });
                });
            }

            // ========== HELPER FUNCTIONS ========== //

            function resetPhotoButton(btn = document.getElementById('take-photo')) {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-camera me-2"></i> Capture';
                }
                const locationProcess = document.getElementById('location-process');
                if (locationProcess) {
                    locationProcess.classList.add('d-none');
                }
            }

            function showSuccessModal(title, message) {
                document.getElementById('success-message').textContent = title;
                document.getElementById('success-details').textContent = message;
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
            }

            function showErrorModal(title, message) {
                document.getElementById('error-title').textContent = title;
                document.getElementById('error-message').textContent = message;
                const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                errorModal.show();
            }

            function formatTime(timeString) {
                if (!timeString) return '';

                try {
                    if (/^\d{2}:\d{2}:\d{2}$/.test(timeString)) {
                        const [hours, minutes] = timeString.split(':');
                        return `${hours}:${minutes}`;
                    }

                    const time = new Date(timeString);
                    if (isNaN(time.getTime())) {
                        return timeString;
                    }
                    return time.toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                } catch (e) {
                    return timeString;
                }
            }

            function formatDate(dateString) {
                if (!dateString) return '';

                try {
                    if (/^\d{4}-\d{2}-\d{2}$/.test(dateString)) {
                        const [year, month, day] = dateString.split('-');
                        return `${month}/${day}/${year}`;
                    }

                    const date = new Date(dateString);
                    if (isNaN(date.getTime())) {
                        return dateString;
                    }
                    return date.toLocaleDateString();
                } catch (e) {
                    return dateString;
                }
            }
    </script>

</body>

</html>