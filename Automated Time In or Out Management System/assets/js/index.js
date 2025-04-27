document.addEventListener("DOMContentLoaded", function() {
    // Load initial data
    loadAttendanceOverview();
    loadRecentHistory();
    setupEventListeners();

    // Load attendance overview stats
    async function loadAttendanceOverview() {
        try {
            const response = await fetch("../api/get-attendance-overview.php");
            const data = await response.json();
            
            if (document.getElementById("total-professors")) {
                document.getElementById("total-professors").textContent = data.total_professors;
            }
            if (document.getElementById("total-attendance")) {
                document.getElementById("total-attendance").textContent = data.total_attendance;
            }
            if (document.getElementById("pending-checkouts")) {
                document.getElementById("pending-checkouts").textContent = data.pending_checkouts;
            }
        } catch (error) {
            console.error("Error loading attendance overview:", error);
        }
    }

    // Load recent history
    async function loadRecentHistory() {
        try {
            const response = await fetch("../api/get-recent-history.php");
            const data = await response.json();
            const historyList = document.getElementById("recent-history-list");
            
            if (!historyList) return;
            
            if (data.length > 0) {
                historyList.innerHTML = data.slice(0, 5).map(item => `
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>${item.name}</strong>
                                <div class="text-muted small">${item.status}</div>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">${item.time}<br>${item.date}</small>
                            </div>
                        </div>
                    </li>
                `).join('');

                const viewMoreBtn = document.getElementById("view-more-btn");
                if (viewMoreBtn) {
                    viewMoreBtn.addEventListener("click", function() {
                        const showingAll = historyList.children.length > 5;
                        
                        historyList.innerHTML = data.slice(0, showingAll ? 5 : data.length).map(item => `
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong>${item.name}</strong>
                                        <div class="text-muted small">${item.status}</div>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted">${item.time}<br>${item.date}</small>
                                    </div>
                                </div>
                            </li>
                        `).join('');

                        viewMoreBtn.innerHTML = showingAll ? 
                            'View More <i class="fas fa-chevron-down ms-2"></i>' : 
                            'View Less <i class="fas fa-chevron-up ms-2"></i>';
                    });
                }
            } else {
                historyList.innerHTML = '<li class="list-group-item text-center text-muted">No records found</li>';
                if (document.getElementById("view-more-btn")) {
                    document.getElementById("view-more-btn").style.display = 'none';
                }
            }
        } catch (error) {
            console.error("Error loading recent history:", error);
        }
    }

    // Setup event listeners
    function setupEventListeners() {
        // Time Out buttons
        document.querySelectorAll(".timeout-btn").forEach(btn => {
            btn.addEventListener("click", function() {
                const professorId = this.getAttribute("data-id");
                handleTimeOut(professorId, this);
            });
        });

        // Search functionality
        document.getElementById("search-professor")?.addEventListener("input", function() {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll("#professor-list .list-group-item").forEach(item => {
                item.style.display = item.textContent.toLowerCase().includes(searchTerm) ? "" : "none";
            });
        });
    }

    // Handle time out
    async function handleTimeOut(professorId, btn) {
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing';

        try {
            const response = await fetch("../api/time-out.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `professor_id=${professorId}`
            });

            const data = await response.json();
            if (data.status !== "success") throw new Error(data.message);

            // Show success
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            document.getElementById('success-message').textContent = 'Time Out Successful';
            document.getElementById('success-details').textContent = `Checked out at ${new Date().toLocaleTimeString()}`;
            successModal.show();

            // Refresh data
            setTimeout(() => location.reload(), 2000);
        } catch (error) {
            console.error("Time out error:", error);
            
            const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
            document.getElementById('error-title').textContent = 'Time Out Failed';
            document.getElementById('error-message').textContent = error.message;
            errorModal.show();
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
});