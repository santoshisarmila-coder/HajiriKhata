/**
 * HajiriKhata Attendance System - Frontend Scripts
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Mobile Sidebar Toggle
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebarToggle && sidebar && overlay) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });

        overlay.addEventListener('click', function () {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    // 2. Auto Dismiss Flash Alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 5000);
    });

    // 3. Quick "Mark All" Attendance Controls
    const markAllPresentBtn = document.getElementById('markAllPresent');
    const markAllAbsentBtn = document.getElementById('markAllAbsent');
    const markAllLateBtn = document.getElementById('markAllLate');

    if (markAllPresentBtn) {
        markAllPresentBtn.addEventListener('click', function () {
            document.querySelectorAll('input[type="radio"][value="Present"]').forEach(function (radio) {
                radio.checked = true;
                highlightRow(radio);
            });
        });
    }

    if (markAllAbsentBtn) {
        markAllAbsentBtn.addEventListener('click', function () {
            document.querySelectorAll('input[type="radio"][value="Absent"]').forEach(function (radio) {
                radio.checked = true;
                highlightRow(radio);
            });
        });
    }

    if (markAllLateBtn) {
        markAllLateBtn.addEventListener('click', function () {
            document.querySelectorAll('input[type="radio"][value="Late"]').forEach(function (radio) {
                radio.checked = true;
                highlightRow(radio);
            });
        });
    }

    // Function to visually accent selected attendance status row
    function highlightRow(radio) {
        const row = radio.closest('tr');
        if (!row) return;
        row.classList.remove('table-success', 'table-danger', 'table-warning');
        if (radio.value === 'Present') row.classList.add('table-success', 'table-opacity-10');
        else if (radio.value === 'Absent') row.classList.add('table-danger', 'table-opacity-10');
        else if (radio.value === 'Late') row.classList.add('table-warning', 'table-opacity-10');
    }

    // Listen to changes on attendance radios
    document.querySelectorAll('.attendance-radio').forEach(function (radio) {
        radio.addEventListener('change', function () {
            highlightRow(this);
        });
    });

    // 4. Quick Live Search for Tables
    const searchInput = document.getElementById('tableSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function () {
            const query = this.value.toLowerCase();
            const table = document.querySelector('.searchable-table tbody');
            if (table) {
                const rows = table.getElementsByTagName('tr');
                for (let i = 0; i < rows.length; i++) {
                    const text = rows[i].textContent.toLowerCase();
                    rows[i].style.display = text.includes(query) ? '' : 'none';
                }
            }
        });
    }

    // 5. Tooltip initialization
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
