<?php
session_name('CABTECH_SYSTEM');
session_start();

if (file_exists('../config/db.php')) {
    include_once('../config/db.php');
}


if (!isset($_SESSION['User_id'])) {
    header("Location: login.php");
    exit;
}


?>


<!DOCTYPE html>
<html lang="en" dir="ltr">

</html>

<head>
    <meta charset="UTF-8">
    <title> CabTech Auto Services - System</title>
    <link rel="stylesheet" href="style/sideBar.css">
    <link rel="stylesheet" href="style/pages.css" id="css-check">
    <link rel="stylesheet" href="style/dataTable.css">
    <link rel="stylesheet" href="style/bootstrap.css">
    <!-- <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"
    /> -->

    <script src="JS/jquery.js"></script>
    <script src="JS/swal.js"></script>
    <script src="JS/dataTable.js"></script>
    <script src="JS/bootstrap.js"></script>
    <script src="JS/select2.js"></script>
    <script src="JS/chartJS.js"></script>

    <!-- Boxiocns CDN Link -->
    <!-- <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'> -->

    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="style/flatpickr.css">

    <!-- Flatpickr JS -->
    <script src="JS/flatpickr.js"></script>
    <!-- <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/en.js"></script> -->


    <script>
        function Logout() {
            Swal.fire({
                title: 'Are you sure?',
                text: 'You will be logged out!',
                iconHtml: '<i class=\'bx bx-help-circle\'></i>',
                showCancelButton: true,
                confirmButtonText: 'Yes, log out!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    sessionStorage.removeItem('currentPage'); // Reset stored page
                    // Redirect to logout.php if confirmed
                    window.location.href = 'logout.php';
                }
            });
        }
        var dashboardPage = "<?php echo isset($_SESSION['dashboardPage']) ? $_SESSION['dashboardPage'] : 'pages/denyAccess.html'; ?>";

        $(document).ready(function() {
            const defaultPage = dashboardPage; // your dashboard fallback

            // Get the last saved page or default
            const initialPage = sessionStorage.getItem('currentPage') || defaultPage;

            // After reload, always load the last saved page into #content-area
            if (initialPage) {
                $.get(initialPage)
                    .done(function(data) {
                        $('#content-area').html(data);
                    })
                    .fail(function(xhr, status, error) {
                        $('#content-area').html(
                            '<p style="color: red; text-align: center;">Error loading page: ' + error + "</p>"
                        );
                    });
            }

            // Handle browser back/forward
            window.onpopstate = function(event) {
                if (event.state && event.state.pageUrl) {
                    loadPage(event.state.pageUrl, false);
                } else {
                    loadPage(defaultPage, false);
                }
            };
        });

        // Function to request page load
        function loadPage(pageUrl, shouldPush = true) {
            // Save the requested page
            sessionStorage.setItem('currentPage', pageUrl);
            
            localStorage.setItem('activeRowIndex', null); // rmeove the active row index on page change

            if (shouldPush) {
                history.pushState({
                    pageUrl: pageUrl
                }, "", "?page=" + encodeURIComponent(pageUrl));
            }

            // Reload the index (forces DataTables reset)
            location.reload();
        }

        function initTooltip() {
            const tooltipElements = document.querySelectorAll('.ellipsis-tooltip');

            tooltipElements.forEach(el => {
                // Destroy existing tooltip instance if it exists
                const existingTooltip = bootstrap.Tooltip.getInstance(el);
                if (existingTooltip) {
                    existingTooltip.dispose();
                }

                const isEllipsed = el.offsetWidth < el.scrollWidth;

                if (isEllipsed) {
                    el.setAttribute('title', el.textContent);
                    el.setAttribute('data-bs-placement', 'bottom');
                    new bootstrap.Tooltip(el);
                } else {
                    el.removeAttribute('title');
                    el.removeAttribute('data-bs-placement');
                }
            });
        }
        // Debounce to limit rapid firing during resize
        var resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                initTooltip();
            }, 200);
        });
    </script>
</head>