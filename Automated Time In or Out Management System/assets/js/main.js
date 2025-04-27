document.addEventListener("DOMContentLoaded", function() {
    // Initialize Webcam
    Webcam.set({
        width: 320,
        height: 240,
        image_format: 'jpeg',
        jpeg_quality: 90,
        constraints: { facingMode: 'user' }
    });

    // Professor selection handler
    const professorSelect = document.getElementById("professor-select");
    const cameraSection = document.getElementById("camera-section");
    
    if (professorSelect && cameraSection) {
        professorSelect.addEventListener("change", function() {
            if (this.value) {
                cameraSection.style.display = "block";
                Webcam.attach("#camera");
            } else {
                cameraSection.style.display = "none";
                Webcam.reset();
            }
        });
    }

    // Photo capture handler
    document.getElementById("take-photo")?.addEventListener("click", async function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing';
        
        try {
            // Get location
            const position = await new Promise((resolve, reject) => {
                const geoOptions = {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                };
                
                navigator.geolocation.getCurrentPosition(resolve, reject, geoOptions);
            });

            // Capture photo
            const dataUri = await new Promise((resolve) => Webcam.snap(resolve));
            
            // Submit to server
            const response = await fetch("../api/checkin.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `professor_id=${professorSelect.value}&image_data=${encodeURIComponent(dataUri)}&latitude=${position.coords.latitude}&longitude=${position.coords.longitude}`
            });
            
            const data = await response.json();
            if (data.status !== "success") throw new Error(data.message);
            
            // Show success and reload
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            document.getElementById('success-message').textContent = 'Check-in Successful';
            document.getElementById('success-details').textContent = `Professor ${data.professor_name} checked in at ${data.check_in_time}`;
            successModal.show();
            
            setTimeout(() => location.reload(), 2000);
        } catch (error) {
            console.error("Check-in error:", error);
            
            const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
            document.getElementById('error-title').textContent = 'Check-in Failed';
            document.getElementById('error-message').textContent = error.message;
            errorModal.show();
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-camera me-2"></i> Capture';
        }
    });

    // Initialize dropdowns in main.js context
    const initDropdowns = function() {
        // Notification dropdown
        const notificationDropdown = new bootstrap.Dropdown(
            document.getElementById('notificationDropdown'), 
            { autoClose: true }
        );
        
        // Profile dropdown
        const profileDropdown = new bootstrap.Dropdown(
            document.getElementById('profileDropdown'), 
            { autoClose: true }
        );

        // Notification dropdown click handler
        document.getElementById('notificationDropdown')?.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            notificationDropdown.toggle();
        });

        // Profile dropdown click handler
        document.getElementById('profileDropdown')?.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            profileDropdown.toggle();
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                notificationDropdown.hide();
                profileDropdown.hide();
            }
        });

        // Close dropdowns when clicking on items
        document.querySelectorAll('.dropdown-menu a')?.forEach(item => {
            item.addEventListener('click', function() {
                notificationDropdown.hide();
                profileDropdown.hide();
            });
        });
    };

    // Call dropdown initialization
    initDropdowns();
});