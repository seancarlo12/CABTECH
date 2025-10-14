<?php

$path = dirname(__DIR__, 1) . '/includes/header.php';
if (file_exists($path)) {
    include_once $path;
    // echo ' File included successfully.';
}

$clientData = [
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'contact_number' => '09',
    'email' => '',
    'address' => ''
];

$isLinked = isset($_SESSION['client_id']) && !empty($_SESSION['client_id']);
$client_id = $_SESSION['client_id'] ?? 0;

if ($isLinked && $client_id > 0) {
    $stmt = $db_connection->prepare("
        SELECT first_name, middle_name, last_name, contact_number, email, address
        FROM clientstbl
        WHERE client_id = ?
    ");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        $clientData = array_merge($clientData, $row);
    }

    $stmt->close();
}

?>

<body>

    <header class="sticky-top">
        <nav class="navbar navbar-expand-lg navbar-white bg-white shadow-sm">
            <div class="containerHead">
                <!-- Logo -->
                <a class="navbar-brand" href="/CABTECH/CAPSTONE - WEBSITE/index.php">
                    <img src="/CABTECH/CAPSTONE - WEBSITE/assets/img/primarylogo.png" alt="logo" style="height: 60px;">
                </a>

                <!-- Hamburger Button -->
                <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#navbarOffcanvas">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <!-- Offcanvas Menu -->
                <div class="offcanvas offcanvas-end" tabindex="-1" id="navbarOffcanvas" aria-labelledby="navbarOffcanvasLabel">
                    <div class="offcanvas-header px-2 pt-3 pb-0">
                        <a href="index.php">
                            <img src="/CABTECH/CAPSTONE - WEBSITE/assets/img/primarylogo.png" alt="logo" style="height: 50px;">
                        </a>
                        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                    </div>

                    <div class="offcanvas-body pt-0">
                        <ul class="navbar-nav mx-auto">
                            <li class="nav-item">
                                <a class="nav-link mx-lg-3" href="/CABTECH/CAPSTONE - WEBSITE/index.php" id="activePage">Home</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link mx-lg-3" href="/CABTECH/CAPSTONE - WEBSITE/pages/servicesPage.php">Services</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link mx-lg-3" href="/CABTECH/CAPSTONE - WEBSITE/pages/location.php">Location</a>
                            </li>
                            <?php if (isset($_SESSION['client_id'])): ?>
                                <!-- 🔔 Notifications Dropdown -->
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle mx-lg-3" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        Notifications
                                        <span id="notif-count" class="badge bg-danger ms-1" style="display:none;">0</span>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown" id="notif-list">

                                    </ul>
                                </li>

                                <!-- 👤 Profile Dropdown -->
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle mx-lg-3" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fa-solid fa-user"></i> <?= isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Account' ?>
                                    </a>
                                    <ul class="dropdown-menu" aria-labelledby="profileDropdown">
                                        <li><a class="dropdown-item" href="/CABTECH/CAPSTONE - WEBSITE/pages/myservices.php">My Services</a></li>
                                        <li><a class="dropdown-item" href="/CABTECH/CAPSTONE - WEBSITE/pages/myaccount.php">Account Settings</a></li>
                                        <li>
                                            <hr class="dropdown-divider">
                                        </li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="Logout()">Logout</a></li>
                                    </ul>
                                </li>
                            <?php else: ?>
                                <!-- 🚪 Not logged in -->
                                <li class="nav-item">
                                    <p class="nav-link mx-lg-3 open-login" style="cursor:pointer;">Log in</p>
                                </li>
                                <li class="nav-item">
                                    <p class="nav-link mx-lg-3 open-signup" style="cursor:pointer;">Sign Up</p>
                                </li>
                            <?php endif; ?>
                            <li class="nav-item mx-lg-3">
                                <button class="schedulebtn" href="#" data-bs-toggle="modal" data-bs-target="#serviceRequestModal">Schedule Service</button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="loginModalLabel">Log in to CabTech</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <!-- Bootstrap alert container -->
                    <div id="login-alert-container"></div>

                    <!-- Log in Form -->
                    <form id="login-form" novalidate>
                        <div class="mb-3">
                            <label for="emailUser" class="form-label">Email or Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" id="emailUser" name="emailUser" class="form-control" placeholder="Enter Email or Username" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="login_password" class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" id="login_password" name="password" class="form-control" placeholder="Enter Password" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword(this)" data-target="#login_password" aria-label="Toggle password visibility">
                                    <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-danger" id="loginBtn">LOGIN</button>
                        </div>

                        <div class="mt-3 text-center">
                            Don't have an account? <a href="#" class="open-signup">Sign Up</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <!-- Signup Modal -->
    <div class="modal fade" id="signupModal" tabindex="-1" aria-labelledby="signupModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="signupModalLabel">Sign up to Cabtech</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">

                    <form id="signup-form">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="signup_username" class="form-label">Username</label>
                                <input id="signup_username" name="username" type="text"
                                    class="form-control"
                                    placeholder="Enter Username" required disabled>
                                <small id="usernameHelp" class="text-danger d-none"></small>
                            </div>

                            <div class="col-md-6">
                                <label for="signup_email" class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input id="signup_email" name="email" type="email"
                                        class="form-control"
                                        placeholder="Enter Email" required>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="signup_f_name" class="form-label">First Name</label>
                                <input id="signup_f_name" name="f_name" type="text" class="form-control" placeholder="Enter First Name" required disabled>
                            </div>

                            <div class="col-md-4">
                                <label for="signup_m_name" class="form-label">Middle Name</label>
                                <input id="signup_m_name" name="m_name" type="text" class="form-control" placeholder="Enter Middle Name" disabled>
                            </div>

                            <div class="col-md-4">
                                <label for="signup_l_name" class="form-label">Last Name</label>
                                <input id="signup_l_name" name="l_name" type="text" class="form-control" placeholder="Enter Last Name" required disabled>
                            </div>

                            <div class="col-md-6">
                                <label for="signup_contact" class="form-label">Contact Number</label>
                                <input id="signup_contact" name="contact_number" type="text" class="form-control" placeholder="Enter Contact Number" disabled>
                            </div>

                            <div class="col-md-6">
                                <label for="signup_address" class="form-label">Address</label>
                                <input id="signup_address" name="address" type="text" class="form-control" placeholder="Enter Address" required disabled>
                            </div>

                            <div class="col-md-6">
                                <label for="signup_password" class="form-label">Password</label>
                                <div class="input-group">
                                    <input id="signup_password" name="password" type="password"
                                        class="form-control"
                                        placeholder="Enter Password" minlength="8"
                                        oninput="validatePassword(this, 'passwordError', 'passwordStrength')"
                                        required disabled autocomplete="off">
                                    <button type="button" class="btn btn-outline-secondary toggle-password" onclick="togglePassword(this)" data-target="#signup_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <!-- These IDs can be anything — just match them in the oninput -->
                                <div id="passwordError" class="mt-2 small"></div>
                                <div id="passwordStrength" class="mt-2 small fw-bold"></div>
                            </div>

                            <div class="col-md-6">
                                <label for="signup_confirm_password" class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <input id="signup_confirm_password" name="confirm_password" type="password"
                                        class="form-control"
                                        placeholder="Confirm Password" required disabled autocomplete="off">
                                    <button type="button" class="btn btn-outline-secondary toggle-password" onclick="togglePassword(this)" data-target="#signup_confirm_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 d-grid gap-2">
                            <button type="submit" name="signup" value="signup" class="btn btn-danger">Verify Email</button>
                        </div>

                        <div class="mt-3 text-center">
                            Already have an account? <a href="#" class="open-login">Log in</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>



    <!-- Modal -->
    <div class="modal fade" id="serviceRequestModal" tabindex="-1" aria-labelledby="serviceRequestLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="serviceRequestLabel">SELECT YOUR SERVICE</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <!-- Step progress bar -->
                    <div class="d-flex justify-content-between align-items-center mb-4" id="progressSteps">
                        <div class="step active" data-step="1"> <span class="text-top">Client Info / Verification</span> 1</div>
                        <div class="line"></div>
                        <div class="step" data-step="2"> <span class="text-top">Vechile Info</span> 2</div>
                        <div class="line"></div>
                        <div class="step" data-step="3"> <span class="text-top">Select Services</span> 3</div>
                        <div class="line"></div>
                        <div class="step" data-step="4"> <span class="text-top">Schedule Appointment</span> 4</div>
                        <div class="line"></div>
                        <div class="step" data-step="5"> <span class="text-top">Confirmation</span> 5</div>
                    </div>
                    <!-- Step 1: Client Info -->
                    <div class="step-content" data-step="1">
                        <div class="mb-3">
                            <label>First Name</label>
                            <input placeholder="Enter First Name" type="text" class="form-control" id="first_name"
                                value="<?= htmlspecialchars($clientData['first_name']) ?>"
                                <?= $isLinked ? 'readonly' : '' ?>>
                        </div>

                        <div class="mb-3">
                            <label>Middle Name</label>
                            <input placeholder="Enter Middle Name" type="text" class="form-control" id="middle_name"
                                value="<?= htmlspecialchars($clientData['middle_name']) ?>"
                                <?= $isLinked ? 'readonly' : '' ?>>
                        </div>

                        <div class="mb-3">
                            <label>Last Name</label>
                            <input placeholder="Enter Last Name" type="text" class="form-control" id="last_name"
                                value="<?= htmlspecialchars($clientData['last_name']) ?>"
                                <?= $isLinked ? 'readonly' : '' ?>>
                        </div>

                        <div class="mb-3">
                            <label>Contact Number</label>
                            <input placeholder="Enter Contact Number" type="text" class="form-control" id="contact_number"
                                value="<?= htmlspecialchars($clientData['contact_number']) ?>"
                                oninput="formatContactNumber(this)"
                                <?= $isLinked ? 'readonly' : '' ?>>
                        </div>

                        <div class="mb-3">
                            <label>Email</label>
                            <input placeholder="Enter Email" type="email" class="form-control" id="email"
                                value="<?= htmlspecialchars($clientData['email']) ?>"
                                <?= $isLinked ? 'readonly' : '' ?>>
                        </div>

                        <div class="mb-3">
                            <label>Address</label>
                            <input placeholder="Enter Address" type="text" class="form-control" id="address"
                                value="<?= htmlspecialchars($clientData['address']) ?>"
                                <?= $isLinked ? 'readonly' : '' ?>>
                        </div>

                        <div class="alert alert-info mt-3" role="alert">
                            <small>
                                <?php if ($isLinked): ?>
                                    <strong>Note:</strong> Your account details are already filled in automatically.
                                    Email verification will be skipped, if you want update your personal information got to your <strong>Account Settings</strong>.
                                <?php else: ?>
                                    <strong>Note:</strong> If you’re an existing customer, please enter the same
                                    <em>email address</em> and/or <em>contact number</em> you used before.
                                    The system will automatically retrieve your saved client information.
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>

                    <!-- Step 2: Vehicle Info -->
                    <div class="step-content d-none" data-step="2">
                        <!-- Existing vehicles (populated after server check) -->
                        <div id="existingVehiclesWrapper" class="mb-3 d-none">
                            <label>Choose an existing vehicle</label>
                            <div class="input-group mb-2">
                                <select id="existingVehicles" class="form-select"></select>
                                <button type="button" class="btn btn-outline-secondary" id="useSelectedVehicleBtn">Use</button>
                            </div>
                            <div>
                                <button type="button" class="btn btn-sm btn-link" id="addNewVehicleBtn">+ Add New Vehicle</button>
                            </div>
                        </div>

                        <!-- New vehicle form (shown if no existing vehicles or user chooses to add new) -->
                        <div id="newVehicleForm">
                            <div class="mb-3">
                                <label>Plate Number</label>
                                <input type="text" class="form-control" id="plate_number">
                            </div>
                            <div class="mb-3">
                                <label>Make</label>
                                <input type="text" class="form-control" id="make">
                            </div>
                            <div class="mb-3">
                                <label>Model</label>
                                <input type="text" class="form-control" id="model">
                            </div>
                            <div class="mb-3">
                                <label>Color</label>
                                <input type="text" class="form-control" id="color">
                            </div>
                            <div class="mb-3">
                                <label>Transmission</label>
                                <select class="form-select" id="transmission_type" onchange="handleOtherOption('transmission_type')">
                                    <option value="">Select transmission type</option>
                                    <option value="Manual">Manual</option>
                                    <option value="Automatic">Automatic</option>
                                    <option value="Other">Other</option>
                                </select>
                                <input type="text" class="form-control mt-2 d-none" id="transmission_type_other" placeholder="Specify transmission type">
                            </div>

                            <div class="mb-3">
                                <label>Fuel Type</label>
                                <select class="form-select" id="fuel_type" onchange="handleOtherOption('fuel_type')">
                                    <option value="">Select fuel type</option>
                                    <option value="Gasoline">Gasoline</option>
                                    <option value="Diesel">Diesel</option>
                                    <option value="Electric">Electric</option>
                                    <option value="Hybrid">Hybrid</option>
                                    <option value="Other">Other</option>
                                </select>
                                <input type="text" class="form-control mt-2 d-none" id="fuel_type_other" placeholder="Specify fuel type">
                            </div>
                            <button type="button" class="btn btn-link" id="backToVehiclesBtn">← Back to Vehicle List</button>
                        </div>
                    </div>

                    <!-- Step 3: Select Services (replaced) -->
                    <div class="step-content d-none" data-step="3">
                        <div class="mb-2 d-flex justify-content-between align-items-center">
                            <div class="input-group w-50">
                                <input id="serviceSearch" type="search" class="form-control form-control-sm" placeholder="Search services (name, desc)...">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="clearServiceSearch">Clear</button>
                            </div>
                        </div>

                        <div id="servicesLoading" class="text-center text-muted small">Loading services…</div>
                        <div id="servicesError" class="d-none text-danger small">Failed to load services.</div>

                        <div id="servicesList" class="pt-2"></div>
                        <div id="servicesEmpty" class="d-none text-muted small">No services match your search.</div>
                    </div>

                    <!-- Step 4: Schedule -->
                    <div class="step-content d-none" data-step="4">
                        <label>Select Date & Time</label>
                        <input type="text" class="form-control" id="schedule_dt" placeholder="Select date & time">
                    </div>

                    <!-- Step 5: Confirm -->
                    <div class="step-content d-none" data-step="5">
                        <h6>Review your details before submitting</h6>
                        <div id="summaryPreview"></div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="prevBtn">Previous</button>
                    <button type="button" class="btn btn-danger" id="nextBtn">Next</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#signup_contact').on('input', function() {
                let val = $(this).val();

                // Remove any non-digit characters
                val = val.replace(/\D/g, '');

                // Force start with "09"
                if (!val.startsWith('09')) {
                    val = '09' + val.replace(/^09/, '');
                }

                // Limit to 11 digits max
                if (val.length > 11) {
                    val = val.slice(0, 11);
                }

                $(this).val(val);

                // Optional: Bootstrap validation styling
                const isValid = /^09\d{9}$/.test(val);
                if (isValid) {
                    $(this).removeClass('is-invalid').addClass('is-valid');
                } else {
                    $(this).removeClass('is-valid').addClass('is-invalid');
                }
            });

            $('#signup_username').on('input', function() {
                let val = $(this).val().trim();
                const help = $('#usernameHelp');

                // Strip invalid chars (only allow letters, numbers, _ and -)
                val = val.replace(/[^a-zA-Z0-9_-]/g, '');
                $(this).val(val);

                let message = '';
                if (val.length === 0) {
                    message = '';
                } else if (val.length < 6) {
                    message = 'Username must be at least 6 characters long.';
                } else if (!/^[a-zA-Z0-9_-]+$/.test(val)) {
                    message = 'Only letters, numbers, underscores (_) and hyphens (-) are allowed.';
                }

                if (message) {
                    $(this).addClass('is-invalid').removeClass('is-valid');
                    help.text(message).removeClass('d-none').addClass('text-danger');
                } else {
                    $(this).addClass('is-valid').removeClass('is-invalid');
                    help.text('').addClass('d-none');
                }
            });
        });
        // 🔹 Reusable password validation
        function validatePassword(input, errorId, strengthId) {
            const password = input.value.trim();
            const errorBox = document.getElementById(errorId);
            const strengthBox = document.getElementById(strengthId);

            // Reset alerts
            errorBox.classList.add('d-none');
            strengthBox.textContent = '';

            // Rules
            const allowedPattern = /^[A-Za-z0-9_]+$/;
            if (password.length > 0 && password.length < 6) {
                return showError('Password must be at least 6 characters long.');
            }
            if (password && !allowedPattern.test(password)) {
                return showError('Only letters, numbers, and underscore (_) are allowed.');
            }

            // Strength logic
            const strength = getPasswordStrength(password);
            if (strength) {
                strengthBox.textContent = `Strength: ${strength}`;
                strengthBox.className = `mt-2 small fw-bold ${getStrengthColor(strength)}`;
            }

            // Helper: show bootstrap alert
            function showError(message) {
                errorBox.textContent = message;
                errorBox.classList.remove('d-none');
                strengthBox.textContent = '';
            }
        }

        // 🔹 Helper: Determine strength label
        function getPasswordStrength(pass) {
            if (!pass) return '';
            const hasLower = /[a-z]/.test(pass);
            const hasUpper = /[A-Z]/.test(pass);
            const hasNumber = /\d/.test(pass);
            const lengthScore = pass.length >= 10 ? 2 : pass.length >= 6 ? 1 : 0;

            const score = [hasLower, hasUpper, hasNumber].filter(Boolean).length + lengthScore;

            if (score <= 2) return 'Weak';
            if (score === 3) return 'Moderate';
            return 'Strong';
        }

        // 🔹 Helper: Apply color style
        function getStrengthColor(strength) {
            switch (strength) {
                case 'Weak':
                    return 'text-danger';
                case 'Moderate':
                    return 'text-warning';
                case 'Strong':
                    return 'text-success';
                default:
                    return '';
            }
        }


        $(document).ready(function() {










            // Prevent dropdown from closing when clicking inside
            $('.dropdown-menu').on('click', function(e) {
                e.stopPropagation();
            });

            function loadNavbarNotifications() {
                $.get('/CABTECH/CAPSTONE - WEBSITE/handlers/fetchNotifications.php', function(response) {
                    const notifList = $('#notif-list');
                    notifList.find('.notif-item, .no-results').remove(); // clear old

                    const notifs = response.notifications || [];
                    const unreadCount = response.count || 0;

                    // Update badge
                    $('#notif-count').text(unreadCount).toggle(unreadCount > 0);

                    if (notifs.length === 0) {
                        notifList.append('<li class="dropdown-item text-center no-results text-muted">No notifications</li>');
                        return;
                    }

                    // Sort by newest first
                    notifs.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));

                    // Use .append() so newest stays at top visually
                    notifs.forEach(n => {
                        const boldClass = n.isRead == 0 ? 'fw-bold' : '';
                        notifList.append(`
                <li class="dropdown-item notif-item ${boldClass}" data-id="${n.notification_id}">
                    <div class="notif-message">${n.message}<br>
                    <small class="text-muted">${formatTimestamp(n.timestamp)}</small></div>
                </li>
            `);
                    });

                }, 'json').fail(function(xhr, status, error) {
                    console.error("loadNavbarNotifications AJAX error:", status, error, xhr.responseText);
                });
            }

            // Format timestamp helper
            function formatTimestamp(ts) {
                const date = new Date(ts);
                return date.toLocaleString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                });
            }

            $('#notif-list').on('click', '.notif-item', function() {
                const li = $(this);
                const notifId = li.data('id');
                const wasUnread = li.hasClass('fw-bold'); // check before AJAX

                $.post('/CABTECH/CAPSTONE - WEBSITE/handlers/markRead.php', {
                    id: notifId
                }, function(response) {
                    if (response.success && wasUnread) { // only reduce if it WAS unread
                        li.removeClass('fw-bold');

                        let count = parseInt($('#notif-count').text()) || 0;
                        let newCount = Math.max(count - 1, 0);
                        $('#notif-count').text(newCount).toggle(newCount > 0);
                    }
                }, 'json');
            });

            // Initial + auto-refresh every 5s
            loadNavbarNotifications();
            setInterval(loadNavbarNotifications, 5000);























            $("#login-form").on("submit", function(e) {
                e.preventDefault();

                const alertContainer = $("#login-alert-container");
                alertContainer.empty();

                const emailUser = $("#emailUser").val().trim();
                const passwordVal = $("#login_password").val().trim();
                const $btn = $("#loginBtn");

                if (!emailUser || !passwordVal) {
                    alertContainer.append(`
                <div class="alert alert-warning alert-dismissible fade show mb-2" role="alert">
                    <strong>⚠️ Missing Fields:</strong> Please fill in both email/username and password.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `);
                    return;
                }

                $btn.prop("disabled", true).text("Logging in...");

                $.ajax({
                    url: "/CABTECH/CAPSTONE - WEBSITE/handlers/login.php",
                    method: "POST",
                    dataType: "json",
                    data: {
                        emailUser: emailUser,
                        password: passwordVal
                    },
                    success: function(res) {
                        console.log("login response:", res);

                        if (!res || !res.success) {
                            alertContainer.append(`
                                <div class="alert alert-danger alert-dismissible fade show mb-2" role="alert">
                                    <strong>❌ Login Failed:</strong> ${res && res.message ? res.message : "Invalid credentials."}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            `);
                            $btn.prop("disabled", false).text("LOGIN");
                            return;
                        }

                        // ✅ Success: redirect or close modal
                        alertContainer.append(`
                            <div class="alert alert-success alert-dismissible fade show mb-2" role="alert">
                                <strong>✅ Login Successful!</strong> Redirecting...
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `);

                        setTimeout(() => {
                            window.location.href = '/CABTECH/CAPSTONE - WEBSITE/pages/myservices.php';
                        }, 1200);
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", error);
                        alertContainer.append(`
                            <div class="alert alert-danger alert-dismissible fade show mb-2" role="alert">
                                <strong>⚠️ Server Error:</strong> ${error || "Something went wrong. Please try again."}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `);
                        $btn.prop("disabled", false).text("LOGIN");
                    }
                });
            });
        });












        function maskEmail(email) {
            if (!email) return '';
            try {
                email = String(email);
                const atIdx = email.indexOf('@');
                if (atIdx === -1) return email;
                const local = email.slice(0, atIdx);
                const domain = email.slice(atIdx);
                const localLen = local.length;
                if (localLen <= 3) return local[0] + '*'.repeat(Math.max(0, localLen - 1)) + domain;
                const showFirstCount = Math.max(1, Math.ceil(localLen * 0.4));
                const showLastCount = Math.min(2, Math.max(0, localLen - showFirstCount));
                const first = local.slice(0, showFirstCount);
                const last = local.slice(localLen - showLastCount);
                const middleLen = Math.max(0, localLen - showFirstCount - showLastCount);
                const middle = '*'.repeat(Math.max(1, middleLen));
                return first + middle + last + domain;
            } catch (e) {
                return email;
            }
        }


        $(document).ready(function() {

            // When the signup button is clicked
            $("button[name='signup']").on("click", function(e) {
                e.preventDefault();
                submitSignupForm(); // call our custom function
            });

        });
        // Main submit function
        function submitSignupForm() {
            const form = document.getElementById("signup-form");
            const $form = $(form);

            // alert container handling
            $('.alert-container').remove();
            const alertContainer = $('<div class="alert-container mt-3"></div>');
            $form.prepend(alertContainer);

            const $btn = $form.find('button[name="signup"]');
            // $btn.prop('disabled', true).text('Processing...');

            // Build a plain object from the form (we'll use selectively)
            const formDataObj = Object.fromEntries(new FormData(form));
            const email = (formDataObj.email || '').trim();

            // If email not verified yet -> start verification-only flow
            if (form.dataset.emailVerified !== 'true') {
                // Minimal email check
                if (!email) {
                    alertContainer.append(`
                <div class="alert alert-danger alert-dismissible fade show mb-2" role="alert">
                    <strong>✉️ Email required:</strong> Please enter your email address.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `);
                    return;
                }
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    alertContainer.append(`
                <div class="alert alert-danger alert-dismissible fade show mb-2" role="alert">
                    <strong>✉️ Invalid Email:</strong> Please enter a valid email address.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `);
                    return;
                }

                // STEP 1: send verification email
                Swal.fire({
                    title: 'Sending verification...',
                    html: 'Please wait while we send a verification code to your email.',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                $.ajax({
                    url: "/CABTECH/CAPSTONE - WEBSITE/handlers/send_verification_email.php",
                    method: "POST",
                    dataType: "json",
                    data: {
                        email: email,
                        allow_existing: 1 // <-- allow sending even if email exists
                    },
                    success: function(sendRes) {
                        Swal.close();
                        console.log("send_verification_email response:", sendRes);

                        if (!sendRes || !sendRes.success) {
                            alertContainer.append(`
                        <div class="alert alert-danger alert-dismissible fade show mb-2" role="alert">
                            <strong>✉️ Verification Error:</strong> ${sendRes && sendRes.message ? sendRes.message : 'Failed to send verification email.'}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `);
                            return;
                        }

                        // Hide modal while asking for code (nice UX)
                        $('#signupModal').modal('hide');

                        // STEP 2: ask user for code
                        Swal.fire({
                            title: 'Enter verification code',
                            html: `<p>We've sent a code to <strong>${maskEmail(email)}</strong>. Enter it below.</p>
                            <input id="verifyCodeInput" class="swal2-input" placeholder="Enter code">`,
                            focusConfirm: false,
                            showCancelButton: true,
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            confirmButtonText: 'Verify',
                            preConfirm: () => {
                                const code = $("#verifyCodeInput").val().trim();
                                if (!code) {
                                    Swal.showValidationMessage('Please enter the verification code.');
                                    return false;
                                }
                                return code;
                            }
                        }).then((swRes) => {
                            // re-show the signup modal for continuity
                            $('#signupModal').modal('show');

                            if (!swRes.isConfirmed) {
                                // $btn.prop('disabled', false).text('SIGN UP');
                                return;
                            }

                            const code = swRes.value;

                            // show loader while verifying
                            Swal.fire({
                                title: 'Verifying code...',
                                allowOutsideClick: false,
                                didOpen: () => Swal.showLoading()
                            });

                            // STEP 3: verify code with server
                            $.ajax({
                                url: "/CABTECH/CAPSTONE - WEBSITE/handlers/verify_email_code.php",
                                method: "POST",
                                dataType: "json",
                                data: {
                                    email: email,
                                    code: code
                                },
                                success: function(vRes) {
                                    Swal.close();
                                    console.log("verify_email_code response:", vRes);

                                    if (!vRes || !vRes.success) {
                                        alertContainer.append(`
                                    <div class="alert alert-danger alert-dismissible fade show mb-2" role="alert">
                                        <strong>🔒 Verification Failed:</strong> ${vRes && vRes.message ? vRes.message : 'Invalid or expired code.'}
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                `);
                                        $btn.prop('disabled', false).text('Verify Email');
                                        return;
                                    }

                                    // ⚠️ If client already has an account, notify user immediately
                                    if (vRes.client && vRes.client.account_id) {
                                        alertContainer.append(`
                                            <div class="alert alert-warning alert-dismissible fade show mb-2" role="alert">
                                                <strong>⚠️ Account Exists:</strong> This email is already linked to an account. Please <a class="open-login">log in</a> or use a different email.
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                            </div>
                                        `);

                                        return;
                                    }

                                    // VERIFIED: mark form state
                                    form.dataset.emailVerified = 'true';
                                    form.dataset.verifiedEmail = email;
                                    if (vRes.client && vRes.client.client_id) {
                                        form.dataset.clientId = vRes.client.client_id;
                                    } else {
                                        form.dataset.clientId = '';
                                    }

                                    $('#signup-form input:not(#signup_email)').prop('disabled', false).removeClass('bg-light');

                                    // If server returned client -> fill from client and lock those fields
                                    if (vRes.client) {
                                        const client = vRes.client;

                                        $('#signup_email').val(client.email || '').prop('readonly', true).addClass('bg-light');
                                        $('#signup_f_name').val(client.first_name || '').prop('readonly', true).addClass('bg-light');
                                        $('#signup_m_name').val(client.middle_name || '').prop('readonly', true).addClass('bg-light');
                                        $('#signup_l_name').val(client.last_name || '').prop('readonly', true).addClass('bg-light');
                                        $('#signup_contact').val(client.contact_number || '').prop('readonly', true).addClass('bg-light');
                                        $('#signup_address').val(client.address || '').prop('readonly', true).addClass('bg-light');

                                        // show notice with Edit button (unlock if needed)
                                        if ($('#prefillNotice').length === 0) {
                                            const notice = $(`
                                                <div id="prefillNotice" class="alert alert-info alert-dismissible fade show mt-2" role="alert">
                                                    <strong>Info:</strong> Email verified. Existing client detected, details have been filled and locked for security.
                                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                                </div>
                                            `);
                                            $form.prepend(notice);
                                        }
                                    } else {
                                        // no client found: keep fields editable but set email readonly (we verified email ownership)
                                        $('#signup_email').val(email).prop('readonly', true).addClass('bg-light');
                                        // other fields remain editable for user to fill (first/last/etc.)
                                        if ($('#prefillNotice').length === 0) {
                                            const notice = $(`
                                        <div id="prefillNotice" class="alert alert-info alert-dismissible fade show mt-2" role="alert">
                                            <strong>Info:</strong> Email verified. Please fill the remaining details. Email is locked.
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    `);
                                            $form.prepend(notice);
                                        }
                                    }

                                    // Inform user verification succeeded and request username+password
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Email Verified',
                                        text: 'You may now choose a username, password and complete the registration.',
                                        confirmButtonText: 'Continue'
                                    }).then(() => {
                                        // focus username so user can continue
                                        $('#signup_username').focus();
                                    });

                                    // re-enable button and change text to "SIGN UP" (now second click will submit)
                                    $btn.prop('disabled', false).text('SIGN UP');
                                },
                                error: function(xhr, status, err) {
                                    Swal.close();
                                    console.error(err);
                                    alertContainer.append(`
                                <div class="alert alert-danger alert-dismissible fade show mb-2" role="alert">
                                    Server error while verifying code. Please try again.
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            `);
                                    $btn.prop('disabled', false).text('SIGN UP');
                                }
                            });
                        }); // end swal then
                    },
                    error: function(xhr, status, err) {
                        Swal.close();
                        console.error(err);
                        alertContainer.append(`
                    <div class="alert alert-danger alert-dismissible fade show mb-2" role="alert">
                        Failed to contact verification service. Please try again.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `);
                        $btn.prop('disabled', false).text('SIGN UP');
                    }
                });

                return; // stop here — user must now fill username/password and click Sign Up again
            } // end verification-first branch

            // ---------- SECOND CLICK: email already verified -> final submission ----------
            // At this point form.dataset.emailVerified === 'true'
            // Require username + password and run validations
            const username = ($('#signup_username').val() || '').trim();
            const password = ($('#signup_password').val() || '');
            const confirmPassword = ($('#signup_confirm_password').val() || '');
            const clientId = form.dataset.clientId || '';

            // Basic username/password checks
            if (!username) {
                alertContainer.append(`
            <div class="alert alert-danger alert-dismissible fade show mb-2" role="alert">
                <strong>Username required:</strong> Please choose a username.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);
                $btn.prop('disabled', false).text('SIGN UP');
                return;
            }
            if (!password) {
                alertContainer.append(`
            <div class="alert alert-danger alert-dismissible fade show mb-2" role="alert">
                <strong>Password required:</strong> Please enter a password.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);
                $btn.prop('disabled', false).text('SIGN UP');
                return;
            }
            if (password.length < 8) {
                alertContainer.append(`
            <div class="alert alert-warning alert-dismissible fade show mb-2" role="alert">
                <strong>Password length:</strong> Password must be at least 8 characters.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);
                $btn.prop('disabled', false).text('SIGN UP');
                return;
            }
            if (password !== confirmPassword) {
                alertContainer.append(`
            <div class="alert alert-warning alert-dismissible fade show mb-2" role="alert">
                <strong>Password mismatch:</strong> Password and re-entered password must match.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);
                $btn.prop('disabled', false).text('SIGN UP');
                return;
            }

            // If this is a new client (no clientId) we should ensure form validity for required fields
            if (!clientId) {
                if (!form.checkValidity()) {
                    form.reportValidity();
                    $btn.prop('disabled', false).text('SIGN UP');
                    return;
                }
                // contact validation again
                const contactNow = ($('#signup_contact').val() || '').trim();
                const isEmptyNow = contactNow === '' || contactNow === '09';
                if (!isEmptyNow && !/^09\d{9}$/.test(contactNow)) {
                    alertContainer.append(`
                <div class="alert alert-danger alert-dismissible fade show mb-2" role="alert">
                    <strong>📱 Invalid Contact Number:</strong> Please enter an 11-digit number starting with 09, or leave it blank.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `);
                    $btn.prop('disabled', false).text('SIGN UP');
                    return;
                }
            }

            // Build payload for signup. If clientId present, include it to link account.
            const payload = Object.fromEntries(new FormData(form));
            if (clientId) payload.client_id = clientId;
            payload.username = username;
            payload.password = password;
            payload.confirm_password = confirmPassword;
            payload.email = form.dataset.verifiedEmail || payload.email || '';

            // final AJAX: create or link account
            $.ajax({
                url: "/CABTECH/CAPSTONE - WEBSITE/handlers/signup.php",
                method: "POST",
                dataType: "json",
                data: payload,
                success: function(signRes) {
                    console.log("signup response:", signRes);
                    if (!signRes || !signRes.success) {
                        // Check if there are actual error messages
                        if (signRes && Array.isArray(signRes.errors) && signRes.errors.length > 0) {
                            signRes.errors.forEach(err => {
                                alertContainer.append(`
                                    <div class="alert alert-danger alert-dismissible fade show mb-2" role="alert">
                                        ${err}
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                `);
                            });
                        } else {
                            // Fallback general message
                            alertContainer.append(`
                                <div class="alert alert-danger alert-dismissible fade show mb-2" role="alert">
                                    ${signRes && signRes.message ? signRes.message : 'Failed to create account. Please try again.'}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            `);
                        }

                        $btn.prop('disabled', false).text('SIGN UP');
                        return;
                    }

                    // success — show professional success dialog and reset form/state
                    Swal.fire({
                        icon: 'success',
                        title: 'Registration Complete',
                        text: signRes.message || 'Your account has been created successfully.',
                        confirmButtonText: 'Continue'
                    }).then(() => {
                        $('#signupModal').modal('hide');
                        form.reset();
                        // remove verified state
                        delete form.dataset.emailVerified;
                        delete form.dataset.verifiedEmail;
                        delete form.dataset.clientId;
                        $('#prefillNotice').remove();
                        // ensure fields unlocked for next time
                        $('#signup_email, #signup_f_name, #signup_m_name, #signup_l_name, #signup_contact, #signup_address')
                            .prop('readonly', false).removeClass('bg-light');
                    });

                    $btn.prop('disabled', false).text('SIGN UP');
                },
                error: function(xhr, status, err) {
                    console.error(err);
                    alertContainer.append(`
                        <div class="alert alert-danger alert-dismissible fade show mb-2" role="alert">
                            Server error while creating account. Please try again later.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `);
                    $btn.prop('disabled', false).text('SIGN UP');
                }
            });
        }


















        flatpickr("#schedule_dt", {
            enableTime: true, // allow time selection
            dateFormat: "Y-m-d H:i", // format for submission
            // minDate: "today", // prevent past dates
            time_24hr: true, // use 24-hour format
            defaultHour: 8, // optional default start time
            defaultMinute: 0
        });


        function validateContactNumber(number) {
            const num = String(number || "").trim();

            // get email value by ID
            const email = $("#email").val().trim();

            // allow empty phone
            if (!num) return true;

            const sanitized = num.replace(/\D/g, ''); // digits only

            // allow "09" only if email is provided
            if (sanitized === "09" && !email) {
                Swal.fire({
                    title: "Invalid Contact Number",
                    text: 'You have to input atleast one email or contact number',
                    icon: "error"
                });
                return false;
            }

            // full 11-digit phone must start with 09
            if (sanitized !== "09" && !/^09\d{9}$/.test(sanitized)) {
                Swal.fire({
                    title: "Invalid Contact Number",
                    text: "Please enter a valid 11-digit number starting with 09.",
                    icon: "error"
                });
                return false;
            }

            return true;
        }


        function formatContactNumber(input) {
            // Remove all non-digit characters
            let value = input.value.replace(/\D/g, "");

            // Ensure it starts with '09'
            if (!value.startsWith("09")) {
                value = "09" + value.replace(/^0+/, ""); // enforce leading '09'
            }

            // Limit to 11 digits
            value = value.substring(0, 11);

            input.value = value;
        }

        function validateEmail() {
            const emailInput = document.getElementById("email");
            const email = emailInput.value.trim();

            // Allow blank email (since either email OR phone is required)
            if (email === "") return true;

            // Basic email pattern
            const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i;

            if (!emailPattern.test(email)) {
                Swal.fire({
                    icon: "error",
                    title: "Invalid Email",
                    text: "Please enter a valid email address (e.g., example@gmail.com).",
                });
                emailInput.focus();
                return false;
            }

            return true;
        }

        function handleOtherOption(type) {
            const select = document.getElementById(type);
            const input = document.getElementById(`${type}_other`);

            // safety check to avoid null errors
            if (!select || !input) return;

            if (select.value === "Other") {
                input.classList.remove("d-none");
                input.focus();
            } else {
                input.classList.add("d-none");
                input.value = "";
            }
        }

        function getSelectedValue(type) {
            const select = document.getElementById(type);
            const input = document.getElementById(`${type}_other`);
            return select.value === "Other" ? input.value.trim() : select.value;
        }


        $(function() {

            // globals
            let currentStep = 1;
            const totalSteps = 5;


            let selectedClient = {
                isNew: true,
                client_id: null
            }; // will be filled after verification
            let selectedVehicle = {
                isNew: true,
                vehicle_id: null
            }; // may be set when choosing existing vehicle

            function showStep(step) {
                $(".step-content").addClass("d-none");
                $(`.step-content[data-step="${step}"]`).removeClass("d-none");

                $(".step").removeClass("active");
                for (let i = 1; i <= step; i++) {
                    $(`.step[data-step="${i}"]`).addClass("active");
                }

                // hide Previous on step 1 and step 2; show it for steps >= 3
                const showPrev = (step > 2);
                $("#prevBtn").toggle(showPrev);

                // update button text
                $("#nextBtn").text(step === totalSteps ? "Submit" : "Next");
            }

            // toggle vehicle UI helpers
            function showExistingVehiclesWrapper(show) {
                if (show) {
                    $("#existingVehiclesWrapper").removeClass("d-none");
                } else {
                    $("#existingVehiclesWrapper").addClass("d-none");
                }
            }

            function showNewVehicleForm(show) {
                if (show) $("#newVehicleForm").show();
                else $("#newVehicleForm").hide();
            }



            // populate existing vehicles select
            function populateVehicles(vehicles) {
                const $sel = $("#existingVehicles");
                $sel.empty();
                if (!vehicles || vehicles.length === 0) {
                    showExistingVehiclesWrapper(false);
                    showNewVehicleForm(true);
                    selectedVehicle = {
                        isNew: true,
                        vehicle_id: null
                    };
                    return;
                }

                // Add a default "Select Vehicle" option
                $sel.append(`<option value="" disabled selected>Select Vehicle</option>`);

                showExistingVehiclesWrapper(true);
                showNewVehicleForm(false); // by default hide new vehicle form
                vehicles.forEach(v => {
                    // display plate + make/model
                    const label = (v.plate_number ? v.plate_number + " — " : "") + v.make + " " + v.model;

                    $sel.append(`
                        <option 
                            value="${v.vehicle_id}"
                            data-plate="${v.plate_number || ''}"
                            data-make="${v.make || ''}"
                            data-model="${v.model || ''}"
                            data-transmission="${v.transmission_type || ''}"
                            data-fuel="${v.fuel_type || ''}"
                        >${label}</option>
                    `);
                });
                // set the first as selected by default
                $sel.prop('selectedIndex', 0);
                selectedVehicle = {
                    isNew: false,
                    vehicle_id: $sel.val()
                };
            }




            // put near top of your script
            const REQUIRE_NEW_EMAIL_VERIFICATION = true; // set to false if you don't want new clients to verify

            function sanitizePhoneForCompare(p) {
                if (!p) return '';
                const digits = String(p).replace(/\D/g, '');
                return digits.slice(-10);
            }

            // ---- MERGED verifyClient that also supports optional new-client verification ----
            function verifyClient() {
                return new Promise((resolve) => {
                    const email = $("#email").val().trim();
                    const rawContact = $("#contact_number").val().trim();
                    const contact = (rawContact === "09" || rawContact === "") ? "" : rawContact;

                    if (!email && !contact) {
                        Swal.fire("Validation", "Please enter at least an email or contact number to verify.", "warning");
                        resolve(false);
                        return;
                    }

                    console.log("verifyClient: starting check for", {
                        email,
                        contact
                    });
                    $("#nextBtn, #prevBtn").prop("disabled", false);
                    Swal.fire({
                        title: 'Checking client...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });

                    // existence check
                    $.ajax({
                        url: "/CABTECH/CAPSTONE - WEBSITE/handlers/check_client.php",
                        method: "POST",
                        dataType: "json",
                        data: {
                            email,
                            contact
                        },
                        success: function(res) {
                            Swal.close();
                            console.log("check_client response:", res);
                            if (!res || typeof res.success === 'undefined') {
                                Swal.fire("Error", "Unexpected response from server when checking client.", "error");
                                $("#nextBtn, #prevBtn").prop("disabled", false);
                                resolve(false);
                                return;
                            }
                            if (!res.success) {
                                Swal.fire("Error", res.message || "Failed to check client.", "error");
                                $("#nextBtn, #prevBtn").prop("disabled", false);
                                resolve(false);
                                return;
                            }

                            // Not exists => new client branch
                            if (!res.exists) {
                                console.log("verifyClient: client not found -> new client flow");
                                selectedClient = {
                                    isNew: true,
                                    client_id: null
                                };
                                populateVehicles([]);
                                showNewVehicleForm(true);

                                // if you require email verification for new clients and email provided, run it now:
                                if (REQUIRE_NEW_EMAIL_VERIFICATION && email) {
                                    // require names before sending email
                                    const firstName = $("#first_name").val().trim(),
                                        lastName = $("#last_name").val().trim();
                                    if (!firstName || !lastName) {
                                        Swal.fire("Missing Information", "- No existing record with this email <br><br> -Please enter both first and last name for a new client before we verify the email.", "warning");
                                        $("#nextBtn, #prevBtn").prop("disabled", false);
                                        resolve(false);
                                        return;
                                    }
                                    console.log("verifyClient: new client requires email verification, sending...");
                                    _sendVerificationToEmail(email, resolve);
                                    return;
                                }

                                $("#nextBtn, #prevBtn").prop("disabled", false);
                                resolve(true); // proceed as new client (no verification required)
                                return;
                            }

                            // Exists -> must verify
                            const serverClient = res.client || null;
                            const serverEmail = serverClient && serverClient.email ? String(serverClient.email).trim() : "";
                            const serverContactSan = serverClient && serverClient.contact_number ? sanitizePhoneForCompare(serverClient.contact_number) : "";
                            const providedContactSan = sanitizePhoneForCompare(contact);

                            console.log("verifyClient: client exists on server", {
                                serverEmail,
                                serverContactSan,
                                providedContactSan
                            });

                            // If the user provided a contact that matches server contact, force the server email usage
                            if (contact && serverContactSan && providedContactSan && serverContactSan === providedContactSan) {
                                if (!serverEmail) {
                                    Swal.fire({
                                        title: "Email required",
                                        html: "We found an account with this contact number but there's <b>no email</b> on file. Please enter the email on the form so we can verify.",
                                        icon: "info"
                                    }).then(() => {
                                        $("#email").focus();
                                        $("#nextBtn, #prevBtn").prop("disabled", false);
                                        resolve(false);
                                    });
                                    return;
                                }

                                // If user typed a different email -> block (force server email OR clear contact)
                                if (email && email.toLowerCase() !== serverEmail.toLowerCase()) {
                                    Swal.fire({
                                        title: "Contact/email mismatch",
                                        html: `This contact number belongs to an existing account. Verification can only be sent to the email on file: <br><strong>${maskEmail(serverEmail)}</strong><br><br>
                                        Either clear the contact field to use a different email, or use the on-file email.`,
                                        icon: "warning",
                                        showCancelButton: true,
                                        confirmButtonText: "Use on-file email",
                                        cancelButtonText: "I'll clear contact"
                                    }).then(ans => {
                                        if (ans.isConfirmed) {
                                            $("#email").val(serverEmail);
                                            _sendVerificationToEmail(serverEmail, resolve);
                                        } else {
                                            $("#contact_number").val('');
                                            $("#email").focus();
                                            $("#nextBtn, #prevBtn").prop("disabled", false);
                                            resolve(false);
                                        }
                                    });
                                    return;
                                }

                                // else: either email wasn't typed, or equals serverEmail -> send to serverEmail
                                _sendVerificationToEmail(serverEmail, resolve);
                                return;
                            }

                            // If we matched by email or the server has an email and no email typed, ask to use server email
                            if (!email && serverEmail) {
                                Swal.fire({
                                    title: "Confirm email for verification",
                                    html: `We found an account that matches. Send verification to: <strong>${maskEmail(serverEmail)}</strong>?`,
                                    icon: "question",
                                    showCancelButton: true,
                                    confirmButtonText: "Yes, send",
                                    cancelButtonText: "No, I'll enter another email"
                                }).then(ans => {
                                    if (ans.isConfirmed) {
                                        $("#email").val(serverEmail);
                                        _sendVerificationToEmail(serverEmail, resolve);
                                    } else {
                                        $("#email").focus();
                                        $("#nextBtn, #prevBtn").prop("disabled", false);
                                        resolve(false);
                                    }
                                });
                                return;
                            }

                            // Otherwise use the typed email (if present) to send verification
                            const targetEmail = email || serverEmail;
                            if (!targetEmail) {
                                Swal.fire("Email required", "We need an email to send the verification code.", "info");
                                $("#nextBtn, #prevBtn").prop("disabled", false);
                                resolve(false);
                                return;
                            }
                            _sendVerificationToEmail(targetEmail, resolve);
                        },
                        error: function(xhr, status, err) {
                            Swal.close();
                            console.error("check_client AJAX error:", status, err, xhr && xhr.responseText);
                            Swal.fire("Error", "Server error while checking client. Try again.", "error");
                            $("#nextBtn, #prevBtn").prop("disabled", false);
                            resolve(false);
                        }
                    });

                    // helper sends code and verifies. Accepts a 'resolve' callback to resolve outer promise.
                    function _sendVerificationToEmail(targetEmail, outerResolve) {
                        console.log("verifyClient: sending code to", targetEmail);
                        Swal.fire({
                            title: 'Sending verification code...',
                            allowOutsideClick: false,
                            didOpen: () => Swal.showLoading()
                        });

                        $.ajax({
                            url: "/CABTECH/CAPSTONE - WEBSITE/handlers/send_verification_email.php",
                            method: "POST",
                            dataType: "json",
                            data: {
                                email: targetEmail,
                                allow_existing: 1 // <-- allow sending even if email exists
                            },
                            success: function(sendRes) {
                                Swal.close();
                                console.log("send_verification_email response:", sendRes);

                                if (!sendRes || !sendRes.success) {
                                    Swal.fire("Error", sendRes && sendRes.message ? sendRes.message : "Failed to send verification email.", "error");
                                    $("#nextBtn, #prevBtn").prop("disabled", false);
                                    outerResolve(false);
                                    return;
                                }


                                // ✅ If skip_verification is true, directly call verify_email_code.php (auto verification)
                                if (sendRes.skip_verification === true) {
                                    console.log("Backend indicated skip_verification -> skipping code input and verifying automatically.");

                                    $.ajax({
                                        url: "/CABTECH/CAPSTONE - WEBSITE/handlers/verify_email_code.php",
                                        method: "POST",
                                        dataType: "json",
                                        data: {
                                            email: targetEmail,
                                            code: "auto" // you can handle this specially in PHP (session check)
                                        },
                                        success: function(vRes) {
                                            console.log("auto verify_email_code response:", vRes);
                                            if (!vRes || !vRes.success) {
                                                Swal.fire("Error", vRes && vRes.message ? vRes.message : "Auto verification failed.", "error");
                                                $("#nextBtn, #prevBtn").prop("disabled", false);
                                                outerResolve(false);
                                                return;
                                            }

                                            // success! apply client + vehicles
                                            const client = vRes.client || null;
                                            const vehicles = vRes.vehicles || [];

                                            if (client) {
                                                selectedClient = {
                                                    isNew: false,
                                                    client_id: parseInt(client.client_id)
                                                };
                                                $("#first_name").val(client.first_name || "");
                                                $("#middle_name").val(client.middle_name || "");
                                                $("#last_name").val(client.last_name || "");
                                                $("#email").val(client.email || "");
                                                $("#contact_number").val(client.contact_number || "");
                                                $("#address").val(client.address || "");
                                            } else {
                                                selectedClient = {
                                                    isNew: true,
                                                    client_id: null
                                                };
                                            }

                                            populateVehicles(vehicles);
                                            outerResolve(true);
                                        },
                                        error: function(xhr, status, err) {
                                            console.error("auto verify_email_code AJAX error:", status, err, xhr && xhr.responseText);
                                            Swal.fire("Error", "Server error during automatic verification.", "error");
                                            $("#nextBtn, #prevBtn").prop("disabled", false);
                                            outerResolve(false);
                                        }
                                    });

                                    return; // ✅ prevent showing the input
                                }

                                // WAIT until modal is fully hidden before showing Swal input
                                $("#serviceRequestModal").one('hidden.bs.modal', function() {
                                    Swal.fire({
                                        title: 'Enter verification code',
                                        html: `<p>We've sent a code to <strong>${maskEmail(targetEmail)}</strong>. Enter it below.</p>
                                        <input id="verifyCodeInput" class="swal2-input" placeholder="6-digit code">`,
                                        focusConfirm: false,
                                        showCancelButton: true,
                                        allowOutsideClick: false, // prevent closing by clicking outside
                                        allowEscapeKey: false, // prevent closing via Esc
                                        confirmButtonText: 'Verify',
                                        preConfirm: () => {
                                            const code = $("#verifyCodeInput").val().trim();
                                            if (!code) {
                                                Swal.showValidationMessage('Please enter the verification code.');
                                                return false;
                                            }
                                            return code;
                                        }
                                    }).then((swRes) => {
                                        // re-show the main modal for UX consistency
                                        $("#serviceRequestModal").modal('show');

                                        if (!swRes.isConfirmed) {
                                            $("#nextBtn, #prevBtn").prop("disabled", false);
                                            outerResolve(false);
                                            return;
                                        }

                                        const code = swRes.value;
                                        $("#nextBtn, #prevBtn").prop("disabled", true);

                                        $.ajax({
                                            url: "/CABTECH/CAPSTONE - WEBSITE/handlers/verify_email_code.php",
                                            method: "POST",
                                            dataType: "json",
                                            data: {
                                                email: targetEmail,
                                                code: code
                                            },
                                            success: function(vRes) {
                                                $("#nextBtn, #prevBtn").prop("disabled", false);
                                                console.log("verify_email_code response:", vRes);
                                                if (!vRes || !vRes.success) {
                                                    Swal.fire("Verification Failed", vRes && vRes.message ? vRes.message : "Invalid code.", "error");
                                                    outerResolve(false);
                                                    return;
                                                }

                                                // success! apply client + vehicles
                                                const client = vRes.client || null;
                                                const vehicles = vRes.vehicles || [];

                                                if (client) {
                                                    selectedClient = {
                                                        isNew: false,
                                                        client_id: parseInt(client.client_id)
                                                    };
                                                    $("#first_name").val(client.first_name || "");
                                                    $("#middle_name").val(client.middle_name || "");
                                                    $("#last_name").val(client.last_name || "");
                                                    $("#email").val(client.email || "");
                                                    $("#contact_number").val(client.contact_number || "");
                                                    $("#address").val(client.address || "");
                                                } else {
                                                    // rare: verified but no client returned
                                                    selectedClient = {
                                                        isNew: true,
                                                        client_id: null
                                                    };
                                                }

                                                populateVehicles(vehicles);
                                                outerResolve(true);
                                            },
                                            error: function(xhr, status, err) {
                                                console.error("verify_email_code AJAX error:", status, err, xhr && xhr.responseText);
                                                $("#nextBtn, #prevBtn").prop("disabled", false);
                                                Swal.fire("Error", "Server error while verifying code.", "error");
                                                outerResolve(false);
                                            }
                                        });
                                    });
                                });

                                // hide the modal to allow swal to take focus (Bootstrap will emit hidden.bs.modal)
                                $("#serviceRequestModal").modal('hide');
                            },
                            error: function(xhr, status, err) {
                                Swal.close();
                                console.error("send_verification_email AJAX error:", status, err, xhr && xhr.responseText);
                                Swal.fire("Error", "Server error while sending verification email.", "error");
                                $("#nextBtn, #prevBtn").prop("disabled", false);
                                outerResolve(false);
                            }
                        });
                    }
                });
            }




            async function validateSchedule() {

                const schedule = $("#schedule_dt").val().trim();
                if (!schedule) {
                    Swal.fire("Missing Schedule", "Please select a schedule before continuing.", "warning");
                    return false;
                }
                const services = [];

                selectedServiceIds.forEach(id => {
                    const s = servicesCache.find(x => String(x.service_id) === id);
                    if (!s) return;

                    const duration = s.estimated_duration || '';
                    if (duration) {
                        const [h, m] = duration.split(':').map(Number);
                    }

                    services.push({
                        service_id: id,
                        duration,
                    });
                });
                console.log(services);



                try {
                    const response = await $.ajax({
                        url: "/CABTECH/CAPSTONE - WEBSITE/handlers/validate_schedule.php", // your PHP endpoint
                        type: 'POST',
                        data: {
                            schedDate: schedule,
                            services: JSON.stringify(services) // serialize array of objects
                        },
                        dataType: 'json'
                    });

                    if (response.status) {
                        return true; // slot available
                    } else {
                        let msg = response.message || "Schedule not available.";
                        if (response.suggested) {
                            const suggestedDate = new Date(response.suggested);
                            // Format as: "Tuesday, Oct 7, 2025 at 14:30"
                            const options = {
                                weekday: 'long',
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit',
                                hour12: true
                            };
                            const formatted = suggestedDate.toLocaleString('en-US', options);
                            msg += `<br>Suggested alternative: <strong>${formatted}</strong>`;
                        }
                        Swal.fire("Schedule Conflict", msg, "warning");
                        return false;
                    }
                } catch (err) {
                    console.error(err);
                    Swal.fire("Error", "Could not validate schedule. Try again.", "error");
                    return false;
                }
            }

            $("#nextBtn").off('click').on('click', async function(e) {
                if (currentStep === 1) {
                    const firstName = $("#first_name").val().trim();
                    const lastName = $("#last_name").val().trim();
                    const email = $("#email").val().trim();
                    const rawContact = $("#contact_number").val().trim();
                    const contact = (rawContact === "09" || rawContact === "") ? "" : rawContact;

                    // If we've already verified or selected an existing client earlier
                    if (selectedClient && !selectedClient.isNew) {
                        currentStep++;
                        if (currentStep === totalSteps) previewSummary();
                        showStep(currentStep);
                        return;
                    }

                    // require at least email or contact
                    if (!email && !contact) {
                        Swal.fire("Missing Contact Info", "Please provide at least an email or contact number.", "warning");
                        return;
                    }

                    // run unified verification (will also verify new clients if REQUIRE_NEW_EMAIL_VERIFICATION)
                    const verified = await verifyClient();
                    if (!verified) return; // stop if verification failed or canceled

                    // if still new client, validate required fields
                    if (selectedClient && selectedClient.isNew) {
                        if (email && !validateEmail()) return;
                        if (contact && !validateContactNumber(contact)) return;

                        if (!firstName || !lastName) {
                            Swal.fire("Missing Information", "Please enter both first and last name for a new client.", "warning");
                            return;
                        }
                    }

                    // proceed
                    currentStep++;
                    if (currentStep === totalSteps) previewSummary();
                    showStep(currentStep);
                    return;
                }





                // --- STEP 2: Validate vehicle (if new user or new vehicle) ---
                if (currentStep === 2) {
                    const isNewVehicle = $("#addVehicleSection").is(":visible") || selectedVehicle?.isNew;

                    if (isNewVehicle) {
                        // Validate NEW vehicle fields
                        const model = $("#model").val().trim();
                        const make = $("#make").val().trim();
                        const transmission_type = getSelectedValue('transmission_type');
                        const fuel_type = getSelectedValue('fuel_type');

                        if (!fuel_type || !transmission_type || !model || !make) {
                            Swal.fire("Incomplete Vehicle Info", "Please fill in all required vehicle fields before continuing.", "warning");
                            e.preventDefault();
                            return;
                        }
                    } else {
                        // Validate EXISTING vehicle selection
                        const selectedVehicleId = $("#existingVehicles").val();
                        if (!selectedVehicleId) {
                            Swal.fire("No Vehicle Selected", "Please select a vehicle from the list before continuing.", "warning");
                            e.preventDefault();
                            return;
                        }
                    }
                }

                // --- STEP 3: Require at least one selected service ---
                if (currentStep === 3) {
                    if (selectedServiceIds.size === 0) {
                        Swal.fire("Choose Service", "Please select at least one service to continue.", "warning");
                        return;
                    }
                }

                // --- STEP 4: Validate schedule selection ---
                if (currentStep === 4) {

                    e.preventDefault(); // prevent default next
                    const valid = await validateSchedule();
                    if (!valid) return; // stop if invalid
                }

                // --- Normal navigation / submission ---
                if (currentStep < totalSteps) {
                    currentStep++;
                    if (currentStep === totalSteps) previewSummary();
                    showStep(currentStep);
                } else {
                    // final step = submit
                    submitRequest();
                }
            });

            $("#prevBtn").on("click", function() {
                if (currentStep > 1) {
                    currentStep--;
                    showStep(currentStep);
                }
            });

            // When user clicks "+ Add New Vehicle"
            $("#addNewVehicleBtn").on("click", function() {
                showNewVehicleForm(true);
                showExistingVehiclesWrapper(false);
                selectedVehicle = {
                    isNew: true,
                    vehicle_id: null
                };
            });

            // When user clicks "← Back to Vehicle List"
            $("#backToVehiclesBtn").on("click", function() {
                showNewVehicleForm(false);
                showExistingVehiclesWrapper(true);

                // Clear all inputs inside the new vehicle form
                $("#newVehicleForm").find("input, select").val("");
            });

            // use existing vehicle button
            $("#useSelectedVehicleBtn").on("click", function() {
                const vid = $("#existingVehicles").val();
                if (!vid) {
                    Swal.fire("Select vehicle", "Please select a vehicle first.", "warning");
                    return;
                }
                selectedVehicle = {
                    isNew: false,
                    vehicle_id: parseInt(vid)
                };
                // ensure new vehicle form hidden
                showNewVehicleForm(false);
                Swal.fire("Vehicle", "Existing vehicle selected.", "success");
            });

            // When user changes selection in the dropdown
            $("#existingVehicles").on("change", function() {
                const selectedVehicleId = $(this).val();

                if (selectedVehicleId) {
                    // Simulate the "Use" button logic automatically
                    $("#useSelectedVehicleBtn").trigger("click");
                }
            });


            function previewSummary() {
                const escapeHtml = str =>
                    String(str || '').replace(/[&<>"']/g, m => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#39;'
                    } [m]));

                const formatMinutesHuman = mins => {
                    mins = Math.max(0, Math.round(mins || 0));
                    const h = Math.floor(mins / 60),
                        m = mins % 60;
                    return h > 0 ? `${h}h ${m}m` : `${m}m`;
                };

                // --- CLIENT INFO ---
                const clientName = `${$("#first_name").val() || ''} ${$("#middle_name").val() || ''} ${$("#last_name").val() || ''}`.replace(/\s+/g, ' ').trim() || '—';
                const email = $("#email").val() || '—';
                const rawContact = $("#contact_number").val().trim();
                const contact = (rawContact === "09" || rawContact === "") ? "—" : rawContact;
                const address = $("#address").val() || '—';

                // --- VEHICLE INFO ---
                let vehicle = {
                    plate: $("#plate_number").val() || $("#existingVehicles option:selected").data('plate') || '—',
                    make: $("#make").val() || $("#existingVehicles option:selected").data('make') || '—',
                    model: $("#model").val() || $("#existingVehicles option:selected").data('model') || '—',
                    transmission: getSelectedValue('transmission_type') || $("#existingVehicles option:selected").data('transmission') || '—',
                    fuel: getSelectedValue('fuel_type') || $("#existingVehicles option:selected").data('fuel') || '—'
                };
                console.log("Selected vehicle for summary:", vehicle);


                // --- SCHEDULE ---
                const scheduleRaw = $("#schedule_dt").val() || '';
                let schedulePretty = '—';
                if (scheduleRaw) {
                    const dt = new Date(scheduleRaw);
                    if (!isNaN(dt)) {
                        const options = {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true
                        };
                        schedulePretty = dt.toLocaleString('en-PH', options);
                    }
                }

                // --- SERVICES ---
                let totalPrice = 0;
                let totalMinutes = 0;
                const services = [];

                selectedServiceIds.forEach(id => {
                    const s = servicesCache.find(x => String(x.service_id) === id);
                    if (!s) return;

                    const name = s.service_name || '—';

                    // remove commas from labor_cost before parsing
                    let price = 0;
                    if (s.labor_cost) {
                        price = parseFloat(s.labor_cost.replace(/,/g, '')) || 0;
                    }

                    totalPrice += price;

                    // parse duration
                    const duration = s.estimated_duration || '';
                    if (duration) {
                        const [h, m] = duration.split(':').map(Number);
                        totalMinutes += (h || 0) * 60 + (m || 0);
                    }

                    services.push({
                        name,
                        price,
                        duration
                    });

                    console.log("Service processed:", {
                        id,
                        name,
                        price,
                        duration
                    });
                });


                // Format for display
                const totalPriceLabel = new Intl.NumberFormat('en-PH', {
                    style: 'currency',
                    currency: 'PHP'
                }).format(totalPrice);
                const totalDurationLabel = formatMinutesHuman(totalMinutes);

                // --- BUILD HTML (2x2 GRID) ---
                let html = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="summary-section mb-3">
                            <h6 class="fw-bold">Client</h6>
                            <p class="mb-1"><strong>Name:</strong> ${escapeHtml(clientName)}</p>
                            <p class="mb-1"><strong>Email:</strong> ${escapeHtml(email)}</p>
                            <p class="mb-1"><strong>Contact:</strong> ${escapeHtml(contact)}</p>
                            <p class="mb-1"><strong>Address:</strong> ${escapeHtml(address)}</p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="summary-section mb-3">
                            <h6 class="fw-bold">Vehicle</h6>
                            <p class="mb-1"><strong>Plate:</strong> ${escapeHtml(vehicle.plate)}</p>
                            <p class="mb-1"><strong>Make:</strong> ${escapeHtml(vehicle.make)}</p>
                            <p class="mb-1"><strong>Model:</strong> ${escapeHtml(vehicle.model)}</p>
                            <p class="mb-1"><strong>Transmission:</strong> ${escapeHtml(vehicle.transmission)}</p>
                            <p class="mb-1"><strong>Fuel:</strong> ${escapeHtml(vehicle.fuel)}</p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="summary-section mb-3">
                            <h6 class="fw-bold">Schedule</h6>
                            <p class="mb-0">${escapeHtml(schedulePretty)}</p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="summary-section mb-3">
                            <h6 class="fw-bold">Services</h6>`;

                if (services.length === 0) {
                    html += `<p class="mb-0">—</p>`;
                } else {
                    html += `<div class="d-flex flex-wrap gap-1 mb-2">`;
                    services.forEach(s => {
                        html += `<span class="badge bg-secondary">${escapeHtml(s.name)}</span>`;
                    });
                    html += `</div>
                    <small class="text-muted">

                        Estimated Duration: ${totalDurationLabel} <br>
                        Estimated Cost: ${totalPriceLabel}
                    </small>`;
                }

                html += `</div></div></div>`;

                // --- RENDER TO DOM ---
                $("#summaryPreview").html(html);
            }

            // Submit — send a single JSON payload to backend so server can insert or use existing client/vehicle
            function submitRequest() {
                // build client object
                const clientObj = selectedClient.isNew ? {
                    isNew: true,
                    first_name: $("#first_name").val(),
                    middle_name: $("#middle_name").val(),
                    last_name: $("#last_name").val(),
                    contact_number: $("#contact_number").val(),
                    email: $("#email").val(),
                    address: $("#address").val()
                } : {
                    isNew: false,
                    client_id: selectedClient.client_id
                };

                // build vehicle object
                const vehicleObj = selectedVehicle.isNew ? {
                    isNew: true,
                    plate_number: $("#plate_number").val(),
                    make: $("#make").val(),
                    model: $("#model").val(),
                    color: $("#color").val(),
                    transmission_type: getSelectedValue('transmission_type'),
                    fuel_type: getSelectedValue('fuel_type')
                } : {
                    isNew: false,
                    vehicle_id: selectedVehicle.vehicle_id
                };

                // services array
                const selectedServices = Array.from(selectedServiceIds).map(id => ({
                    service_id: id,
                    clients_comment: selectedServiceComments[id] || '' // use empty string if no comment
                }));

                const schedule = $("#schedule_dt").val();
                const payload = {
                    client: clientObj,
                    vehicle: vehicleObj,
                    services: selectedServices,
                    schedule: schedule,
                    request_type: "Appointment", // match enum
                    request_status: "Pending" // or Pending depending on business rules
                };
                console.log("Submitting payload:", payload);
                $("#nextBtn").prop("disabled", true);

                $.ajax({
                    url: "/CABTECH/CAPSTONE - WEBSITE/handlers/submit_request.php",
                    method: "POST",
                    dataType: "json",
                    data: {
                        payload: JSON.stringify(payload)
                    },
                    success: function(res) {
                        if (res && res.success) {
                            Swal.fire("Success", res.message || "Request created successfully.", "success").then(() => {
                                // Hide modal
                                $("#serviceRequestModal").modal("hide");

                                $("#serviceRequestModal input, #serviceRequestModal select, #serviceRequestModal textarea").val("");
                                $("#serviceRequestModal input[type=checkbox], #serviceRequestModal input[type=radio]").prop("checked", false);

                                selectedClient = null;
                                selectedVehicle = null;
                                selectedServiceIds = new Set();
                                request_Details = null;
                                request_servicesInfo = null;
                                request_servicesDetails = null;
                                request_Client = null;
                                request_Vehicle = null;

                                currentStep = 1;
                                showStep(currentStep);

                                if (typeof refreshRequestList === "function") {
                                    refreshRequestList(); // refresh request table/list if exists
                                }
                                if (typeof refreshCalendar === "function") {
                                    refreshCalendar(); // refresh calendar view if exists
                                }
                            });
                        } else {
                            Swal.fire("Error", res.message || "Failed to create request.", "error");
                        }
                    },
                    error: function(xhr, status, err) {
                        console.error(xhr.responseText);
                        Swal.fire("Error", "Server error while submitting request.", "error");
                    },
                    complete: function() {
                        $("#nextBtn").prop("disabled", false);
                    }
                });
            }

            // initialize UI state
            showNewVehicleForm(true);
            showStep(currentStep);



            // Checkbox toggle
            $(document).on('change', '.service-check', function() {
                const id = String($(this).val());
                if ($(this).is(':checked')) {
                    selectedServiceIds.add(id);
                    // enable comment input
                    $(`#service-${id}`).closest('tr').find('.service-comment').prop('disabled', false);
                } else {
                    selectedServiceIds.delete(id);
                    // disable and clear comment input
                    const input = $(`#service-${id}`).closest('tr').find('.service-comment');
                    selectedServiceComments[id] = input.val().trim(); // preserve previous comment
                    input.prop('disabled', true);
                }
                previewSummary();
            });

            // Comment input change
            $(document).on('input', '.service-comment', function() {
                const row = $(this).closest('tr');
                const id = row.find('.service-check').val();
                selectedServiceComments[id] = $(this).val().trim();
                previewSummary();
            });


        });


        // --- Services dynamic loader / UI helpers ---
        let servicesCache = []; // holds latest services from server
        let selectedServiceIds = new Set(); // preserve selections across reloads
        let selectedServiceComments = {}; // { service_id: comment }

        function formatCurrency(n) {
            if (n == null || n === '') return '';
            try {
                // Remove commas or spaces
                const num = Number(String(n).replace(/,/g, '').trim());
                if (isNaN(num)) return '';
                return new Intl.NumberFormat('en-PH', {
                    style: 'currency',
                    currency: 'PHP'
                }).format(num);
            } catch (e) {
                return '₱' + Number(n).toFixed(2);
            }
        }

        function escapeHtml(s) {
            if (!s && s !== 0) return '';
            return String(s).replace(/[&<>"'`=\/]/g, function(c) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;',
                    '`': '&#96;',
                    '=': '&#61;',
                    '/': '&#47;'
                } [c];
            });
        }
        // Convert "HH:MM" or "H:MM:SS" or minutes number to human-readable string
        function formatDurationWords(duration) {
            if (!duration) return '';

            let totalMinutes = 0;

            if (typeof duration === 'string') {
                const parts = duration.split(':').map(Number);
                if (parts.length === 2) {
                    // HH:MM
                    totalMinutes = (parts[0] || 0) * 60 + (parts[1] || 0);
                } else if (parts.length === 3) {
                    // HH:MM:SS
                    totalMinutes = (parts[0] || 0) * 60 + (parts[1] || 0) + Math.round((parts[2] || 0) / 60);
                } else if (!isNaN(Number(duration))) {
                    // plain number of minutes
                    totalMinutes = Number(duration);
                }
            } else if (typeof duration === 'number') {
                totalMinutes = duration;
            }

            if (totalMinutes <= 0) return '0 minutes';

            const days = Math.floor(totalMinutes / (24 * 60));
            const hours = Math.floor((totalMinutes % (24 * 60)) / 60);
            const minutes = totalMinutes % 60;

            let result = [];
            if (days) result.push(`${days} day${days > 1 ? 's' : ''}`);
            if (hours) result.push(`${hours} hour${hours > 1 ? 's' : ''}`);
            if (minutes) result.push(`${minutes} minute${minutes > 1 ? 's' : ''}`);

            return result.join(' ');
        }

        function renderServicesList(filtered) {
            const $list = $("#servicesList");
            $list.empty();
            if (!filtered || filtered.length === 0) {
                $("#servicesEmpty").removeClass('d-none');
                return;
            } else {
                $("#servicesEmpty").addClass('d-none');
            }

            // Table header
            let html = `
        <table class="servicesTable table table-sm align-middle">
            <thead class="table-danger">
                <tr>
                    <th scope="col">Select</th>
                    <th scope="col">Service Name</th>
                    <th scope="col">Estimated Duration</th>
                    <th scope="col">Labor Cost</th> 
                    <th scope="col">Describe your problem (Optional)</th> 
                </tr>
            </thead>
            <tbody>
    `;

            filtered.forEach(s => {
                const id = `service-${s.service_id}`;
                const checked = selectedServiceIds.has(String(s.service_id)) ? 'checked' : '';

                const durationLabel = s.estimated_duration ?
                    formatDurationWords(s.estimated_duration) :
                    '—';

                const laborLabel = (s.labor_cost !== null && s.labor_cost !== undefined && s.labor_cost !== '') ?
                    formatCurrency(s.labor_cost) :
                    '—';

                html += `
                    <tr>
                        <td class="text-center">
                            <input class="form-check-input service-check" 
                                type="checkbox" 
                                value="${escapeHtml(s.service_id)}" 
                                id="${id}" 
                                data-duration="${escapeHtml(s.duration || '')}"
                                data-estimated_duration="${escapeHtml(s.estimated_duration || '')}"
                                data-labor_cost="${escapeHtml(s.labor_cost || '')}"
                                ${checked}>
                        </td>
                        <td>${escapeHtml(s.service_name)}</td>
                        <td>${durationLabel}</td>
                        <td>${laborLabel}</td>
                        <td>
                            <input type="text" 
                                class="form-control form-control-sm service-comment" 
                                placeholder="Add comment..." 
                                value="${escapeHtml(selectedServiceComments[s.service_id] || '')}"
                                ${!checked ? 'disabled' : ''}>
                        </td>
                    </tr>
                    `;
            });

            html += `</tbody></table>`;
            $list.html(html);
        }

        function populateServices(services) {
            // keep only active services if `active` flag exists
            servicesCache = (services || []).filter(s => s.active === undefined || Number(s.active) === 1);
            // default sort by name
            servicesCache.sort((a, b) => (a.service_name || '').localeCompare(b.service_name || ''));
            renderServicesList(servicesCache);
        }

        function loadServices() {
            $("#servicesError").addClass('d-none');
            $("#servicesLoading").show();
            $.ajax({
                url: "/CABTECH/CAPSTONE - WEBSITE/handlers/get_services.php",
                method: "GET",
                dataType: "json",
                success: function(res) {
                    if (!res || !res.success || !Array.isArray(res.services)) {
                        $("#servicesError").removeClass('d-none').text(res && res.message ? res.message : "No services found.");
                        // fallback: if you want a static fallback, set populateServices([{service_id:1, service_name:'Oil Change', price:150}, ...])
                        return;
                    }
                    populateServices(res.services);
                },
                error: function() {
                    $("#servicesError").removeClass('d-none').text("Could not load services (server error).");
                },
                complete: function() {
                    $("#servicesLoading").hide();
                }
            });
        }

        // filtering (client-side)
        function filterServices(query) {
            if (!query) {
                renderServicesList(servicesCache);
                return;
            }
            const q = query.trim().toLowerCase();
            const filtered = servicesCache.filter(s => {
                return (s.service_name && s.service_name.toLowerCase().includes(q)) ||
                    (s.description && s.description.toLowerCase().includes(q)) ||
                    (s.service_id && String(s.service_id) === q);
            });
            renderServicesList(filtered);
        }

        $("#serviceSearch").on('input', (function() {
            let t = null;
            return function() {
                clearTimeout(t);
                const val = $(this).val();
                t = setTimeout(() => filterServices(val), 200);
            };
        })());

        $("#clearServiceSearch").on('click', function() {
            $("#serviceSearch").val('');
            filterServices('');
        });
        // preserve checks across reloads: before load, collect any currently checked
        function preserveSelectedBeforeReload() {
            selectedServiceIds = new Set();
            $(".service-check:checked").each(function() {
                selectedServiceIds.add(String($(this).val()));
            });
        }

        // call loadServices when modal opens (so we always have fresh list)
        $('#serviceRequestModal').on('show.bs.modal', function() {
            preserveSelectedBeforeReload();
            loadServices();
        });

        // Optional: also load on page ready (comment/uncomment as you prefer)
        $(function() {
            // loadServices(); // uncomment if you want services loaded even before modal opens
        });
















        // When any element with the .open-login class is clicked
        $(document).on('click', '.open-login', function(e) {
            e.preventDefault(); // prevent default action (like link navigation)

            $('#signupModal').modal('hide');
            $('#loginModal').modal('show');
        });

        // When any element with the .open-login class is clicked
        $(document).on('click', '.open-signup', function(e) {
            e.preventDefault(); // prevent default action (like link navigation)

            $('#loginModal').modal('hide');
            $('#signupModal').modal('show');
        });






        function togglePassword(btn) {
            const targetSelector = btn.getAttribute('data-target');
            const target = document.querySelector(targetSelector);
            if (!target) return; // no target found

            const icon = btn.querySelector('i');
            const isPassword = target.type === 'password';

            // Toggle the input type
            target.type = isPassword ? 'text' : 'password';

            // Update icon if present
            if (icon) {
                icon.classList.toggle('fa-eye', !isPassword);
                icon.classList.toggle('fa-eye-slash', isPassword);
            }
        }

        // Get current file name (e.g., index.php)
        const currentPage = window.location.pathname.split("/").pop();

        // Select all nav links and dropdown items
        const navLinks = document.querySelectorAll('.navbar-nav .nav-link, .dropdown-item');

        navLinks.forEach(link => {
            // Get the file name part of the href
            const href = link.getAttribute('href');
            if (!href || href === '#') return;

            const linkPage = link.getAttribute('href').split("/").pop();

            // If file names match, add active class
            if (linkPage === currentPage) {
                link.classList.add('active');

                // If link is in a dropdown, also add active to parent toggle
                const dropdown = link.closest('.dropdown');
                if (dropdown) {
                    const toggle = dropdown.querySelector('.dropdown-toggle');
                    if (toggle) toggle.classList.add('active');
                }
            }
        });
    </script>