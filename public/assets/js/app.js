/**
 * Document Monitoring System - application JavaScript.
 */
(function () {
    'use strict';

    // Sidebar toggle (mobile)
    var toggle = document.getElementById('sidebarToggle');
    if (toggle) {
        toggle.addEventListener('click', function () {
            var sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                if (getComputedStyle(sidebar).display === 'none') {
                    sidebar.style.display = 'flex';
                } else {
                    sidebar.style.display = 'none';
                }
            }
        });
    }

    // Chart.js rendering for admin dashboard
    function initDashboardCharts() {
        if (typeof Chart === 'undefined' || !window.dms_charts) return;
        var c = window.dms_charts;

        if (document.getElementById('statusChart')) {
            new Chart(document.getElementById('statusChart'), {
                type: 'doughnut',
                data: {
                    labels: c.statusLabels,
                    datasets: [{ data: c.status,
                        backgroundColor: ['#ffc107', '#0d6efd', '#198754', '#dc3545', '#0dcaf0', '#6c757d'] }]
                },
                options: { plugins: { legend: { position: 'bottom' } } }
            });
        }
        if (document.getElementById('dayChart')) {
            new Chart(document.getElementById('dayChart'), {
                type: 'bar',
                data: {
                    labels: c.dayLabels,
                    datasets: [{ label: 'Documents', data: c.days, backgroundColor: '#0d6efd' }]
                },
                options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
        }
        if (document.getElementById('holderChart')) {
            new Chart(document.getElementById('holderChart'), {
                type: 'pie',
                data: {
                    labels: c.holderLabels,
                    datasets: [{ data: c.holder,
                        backgroundColor: ['#198754', '#0d6efd', '#ffc107', '#dc3545'] }]
                },
                options: { plugins: { legend: { position: 'bottom' } } }
            });
        }
        if (document.getElementById('branchChart')) {
            new Chart(document.getElementById('branchChart'), {
                type: 'bar',
                data: {
                    labels: c.branchLabels,
                    datasets: [{ label: 'Documents', data: c.branch, backgroundColor: '#0dcaf0' }]
                },
                options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
        }
    }

    // Notification polling (if API available)
    function pollNotifications() {
        var path = window.DMS_BASE_URL || '';
        fetch(path + '/api/notifications.php', { headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var badge = document.querySelector('.notification-badge');
                if (badge) {
                    if (data.unread > 0) {
                        badge.textContent = data.unread;
                        badge.style.display = 'inline';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            })
            .catch(function () { /* ignore polling errors */ });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initDashboardCharts();
        initDataTables();
        // Poll every 60s silently
        if (window.DMS_POLL) {
            setInterval(pollNotifications, 60000);
        }
    });

    // DataTables enhancement for tables marked .data-table (requires jQuery + DataTables loaded)
    function initDataTables() {
        if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') return;
        jQuery.each(jQuery('.data-table'), function () {
            var t = jQuery(this).DataTable({
                pageLength: 15,
                lengthChange: false,
                order: [[0, 'desc']]
            });
        });
    }

    window.DMS = { initDashboardCharts: initDashboardCharts };
})();