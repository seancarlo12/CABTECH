<?php

session_name('CABTECH_WEBSITE');
session_start();
// Check if the file exists before including
if (file_exists('../includes/header.php')) {

    include_once '../includes/header.php';
}



include_once '../includes/headNav.php';


// ensure logged in (client_id session)
$client_id = $_SESSION['client_id'] ?? null;
if (!$client_id) {
    echo '<div class="alert alert-warning">You must be logged in to view your account. <a href="#" class="open-login">Log in</a></div>';
    echo '</section></main></body></html>';
    exit;
}


$account_id = $_SESSION['account_id'];

// Fetch account info
$stmt = $db_connection->prepare("SELECT username, password, date_created FROM accountstbl WHERE account_id = ?");
$stmt->bind_param("i", $account_id);
$stmt->execute();
$accountResult = $stmt->get_result();
$account = $accountResult->fetch_assoc();

// Fetch client info
$stmt2 = $db_connection->prepare("SELECT first_name, last_name, middle_name, contact_number, email, address FROM clientstbl WHERE account_id = ?");
$stmt2->bind_param("i", $account_id);
$stmt2->execute();
$clientResult = $stmt2->get_result();
$client = $clientResult->fetch_assoc();

// Fetch vehicles
$stmt3 = $db_connection->prepare("SELECT vehicle_id, make, model, plate_number, color, transmission_type, fuel_type FROM vehiclestbl WHERE client_id = ? AND status = 'active'");
$stmt3->bind_param("i", $client_id);
$stmt3->execute();
$vehicleResult = $stmt3->get_result();
$vehicles = [];
while ($row = $vehicleResult->fetch_assoc()) {
    $vehicles[] = $row;
}
$stmt3->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CabTech Auto Services - Account Settings</title>
    <link rel="stylesheet" href="../style/myaccount.css">
</head>

<body>

    <main>
        <div class="container">
            <!-- Personal Information -->
            <div class="section">
                <h2>
                    Personal Information
                    <button class="edit-btn" id="editProfileBtn">Edit Profile</button>
                    <button class="save-btn" id="saveProfileBtn" style="display:none;">Save</button>
                    <button class="cancel-btn" id="cancelProfileBtn" style="display:none;">Cancel</button>
                </h2>

                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input id="first_name" type="text" placeholder="First Name" value="<?php echo htmlspecialchars($client['first_name'] ?? ''); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="contact_number">Phone Number</label>
                        <input id="contact_number" type="text" placeholder="Phone Number" value="<?php echo htmlspecialchars($client['contact_number'] ?? ''); ?>" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input id="last_name" type="text" placeholder="Last Name" value="<?php echo htmlspecialchars($client['last_name'] ?? ''); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input id="email" type="email" placeholder="Email" value="<?php echo htmlspecialchars($client['email'] ?? ''); ?>" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input id="middle_name" type="text" placeholder="Middle Name" value="<?php echo htmlspecialchars($client['middle_name'] ?? ''); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <input id="address" type="text" placeholder="Address" value="<?php echo htmlspecialchars($client['address'] ?? ''); ?>" readonly>
                    </div>
                </div>
            </div>

            <!-- Account Information -->
            <div class="section">
                <h2>Your Account <button id="editAccountBtn" class="edit-btn">Edit Account</button></h2>

                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input id="username" type="text" placeholder="Username" value="<?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : ''; ?>" readonly>
                    </div>
                </div>

                <!-- Password fields hidden initially -->
                <div id="passwordFields" style="display:none;">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <div class="password-wrapper">
                            <input id="current_password" type="password" placeholder="Enter current password">
                            <button type="button" class="toggle-pw"><i class="fa-regular fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="password-wrapper">
                            <input id="new_password" type="password" placeholder="Enter new password">
                            <button type="button" class="toggle-pw"><i class="fa-regular fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_new_password">Confirm New Password</label>
                        <div class="password-wrapper">
                            <input id="confirm_new_password" type="password" placeholder="Confirm new password">
                            <button type="button" class="toggle-pw"><i class="fa-regular fa-eye"></i></button>
                        </div>
                    </div>
                </div>

                <div class="action-buttons" style="display:none;">
                    <button id="saveAccountBtn">Save Changes</button>
                    <button id="cancelAccountBtn">Cancel</button>
                </div>

                <div class="created-date">
                    Date Created: <strong><?php echo date("F j, Y", strtotime($account['date_created'] ?? '')); ?></strong>
                </div>
            </div>


            <!-- Vehicles -->
            <div class="section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="mb-0">Your Vehicle/s</h2>
                    <button type="button" class="btn btn-primary btn-sm" id="addVehicleBtn">
                        <i class="bi bi-plus-circle me-1"></i> Add Vehicle
                    </button>
                </div>
                <?php if (!empty($vehicles)): ?>
                    <?php foreach ($vehicles as $v): ?>
                        <div class="vehicle" data-id="<?= $v['vehicle_id'] ?>">
                            <span>
                                <strong><?= htmlspecialchars($v['make'] . ' ' . $v['model']) ?></strong>
                                <?= $v['plate_number'] ? ' / ' . htmlspecialchars($v['plate_number']) : '' ?>
                            </span>
                            <div>
                                <button class="edit-vehicle" data-vehicle-id="<?= $v['vehicle_id'] ?>">
                                    <i class="fa-regular fa-pen-to-square"></i>
                                </button>
                                <button class="delete-vehicle" data-vehicle-id="<?= $v['vehicle_id'] ?>">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No vehicles added yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div class="modal fade" id="editVehicleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Vehicle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="vehicle_id">
                    <div class="mb-3">
                        <label for="vehicle_make">Make</label>
                        <input type="text" id="vehicle_make" class="form-control" placeholder="Enter Vehicle Make">
                    </div>
                    <div class="mb-3">
                        <label for="vehicle_model">Model</label>
                        <input type="text" id="vehicle_model" class="form-control" placeholder="Enter Vehicle Model">
                    </div>
                    <div class="mb-3">
                        <label for="vehicle_plate">Plate Number</label>
                        <input type="text" id="vehicle_plate" class="form-control" placeholder="Enter Vehicle Plate Number">
                    </div>
                    <div class="mb-3">
                        <label for="vehicle_color">Color</label>
                        <input type="text" id="vehicle_color" class="form-control" placeholder="Enter Vehicle Color">
                    </div>
                    <div class="mb-3">
                        <label for="vehicle_transmission">Transmission</label>
                        <select id="vehicle_transmission" class="form-select">
                            <option value="Automatic">Automatic</option>
                            <option value="Manual">Manual</option>
                            <option value="Other">Other</option>
                        </select>
                        <input type="text" id="vehicle_transmission_other" class="form-control mt-2" placeholder="Enter transmission type" style="display:none;">
                    </div>
                    <div class="mb-3">
                        <label for="vehicle_fuel">Fuel Type</label>
                        <select id="vehicle_fuel" class="form-select">
                            <option value="Gasoline">Gasoline</option>
                            <option value="Diesel">Diesel</option>
                            <option value="Electric">Electric</option>
                            <option value="Hybrid">Hybrid</option>
                            <option value="Other">Other</option>
                        </select>
                        <input type="text" id="vehicle_fuel_other" class="form-control mt-2" placeholder="Enter fuel type" style="display:none;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="saveVehicleBtn" class="btn btn-primary">Save Changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>


    <div class="modal fade" id="addVehicleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Vehicle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add_vehicle_make">Make</label>
                        <input type="text" id="add_vehicle_make" class="form-control" placeholder="Enter Vehicle Make">
                    </div>
                    <div class="mb-3">
                        <label for="add_vehicle_model">Model</label>
                        <input type="text" id="add_vehicle_model" class="form-control" placeholder="Enter Vehicle Model">
                    </div>
                    <div class="mb-3">
                        <label for="add_vehicle_plate">Plate Number</label>
                        <input type="text" id="add_vehicle_plate" class="form-control" placeholder="Enter Vehicle Plate Number">
                    </div>
                    <div class="mb-3">
                        <label for="add_vehicle_color">Color</label>
                        <input type="text" id="add_vehicle_color" class="form-control" placeholder="Enter Vehicle Color">
                    </div>
                    <div class="mb-3">
                        <label for="add_vehicle_transmission">Transmission</label>
                        <select id="add_vehicle_transmission" class="form-select">
                            <option value="Automatic">Automatic</option>
                            <option value="Manual">Manual</option>
                            <option value="Other">Other</option>
                        </select>
                        <input type="text" id="add_vehicle_transmission_other" class="form-control mt-2" placeholder="Enter transmission type" style="display:none;">
                    </div>
                    <div class="mb-3">
                        <label for="add_vehicle_fuel">Fuel Type</label>
                        <select id="add_vehicle_fuel" class="form-select">
                            <option value="Gasoline">Gasoline</option>
                            <option value="Diesel">Diesel</option>
                            <option value="Electric">Electric</option>
                            <option value="Hybrid">Hybrid</option>
                            <option value="Other">Other</option>
                        </select>
                        <input type="text" id="add_vehicle_fuel_other" class="form-control mt-2" placeholder="Enter fuel type" style="display:none;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="addVehicleSaveBtn" class="btn btn-primary">Add Vehicle</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>


    <script>
        $(document).ready(function() {

            $('#addVehicleBtn').on('click', function() {
                $('#addVehicleModal').modal('show');
                // $('#editVehicleModal').modal('show');

            });





            $('#addVehicleSaveBtn').on('click', function() {
                let make = $('#add_vehicle_make').val().trim();
                let model = $('#add_vehicle_model').val().trim();
                let plate = $('#add_vehicle_plate').val().trim();
                let color = $('#add_vehicle_color').val().trim();


                let transmission = $('#add_vehicle_transmission').val();
                if (transmission === 'Other') transmission = $('#add_vehicle_transmission_other').val().trim();

                let fuel = $('#add_vehicle_fuel').val();
                if (fuel === 'Other') fuel = $('#add_vehicle_fuel_other').val().trim();

                // Validate
                if (!make || !model || !transmission || !fuel) {
                    Swal.fire('Validation', 'missing required fields.', 'warning');
                    return;
                }
                console.log(make, model, plate, color, transmission, fuel);

                $.ajax({
                    url: '/CABTECH/CAPSTONE - WEBSITE/handlers/manageAccount.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'addVehicle',
                        make: make,
                        model: model,
                        plate_number: plate,
                        color: color,
                        transmission_type: transmission,
                        fuel_type: fuel
                    },
                    success: function(res) {
                        if (res.success) {
                            Swal.fire('Success', res.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', res.message || 'Failed to add vehicle.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Server error while adding vehicle.', 'error');
                    }
                });
            });








            // Show/hide "Other" input for Add Vehicle modal
            $('#add_vehicle_transmission').on('change', function() {
                if ($(this).val() === 'Other') {
                    $('#add_vehicle_transmission_other').show().val('');
                } else {
                    $('#add_vehicle_transmission_other').hide().val('');
                }
            });

            $('#add_vehicle_fuel').on('change', function() {
                if ($(this).val() === 'Other') {
                    $('#add_vehicle_fuel_other').show().val('');
                } else {
                    $('#add_vehicle_fuel_other').hide().val('');
                }
            });


            // Show/hide "Other" input
            $('#vehicle_transmission').on('change', function() {
                if ($(this).val() === 'Other') {
                    $('#vehicle_transmission_other').show().val('');
                } else {
                    $('#vehicle_transmission_other').hide().val('');
                }
            });

            $('#vehicle_fuel').on('change', function() {
                if ($(this).val() === 'Other') {
                    $('#vehicle_fuel_other').show().val('');
                } else {
                    $('#vehicle_fuel_other').hide().val('');
                }
            });

            // Open edit modal and fetch vehicle info
            $('.edit-vehicle').on('click', function() {
                let vehicleId = $(this).data('vehicle-id');

                $.ajax({
                    url: '/CABTECH/CAPSTONE - WEBSITE/handlers/manageAccount.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'getVehicle',
                        vehicle_id: vehicleId
                    },
                    success: function(res) {
                        if (res.success) {
                            $('#vehicle_id').val(res.data.vehicle_id);
                            $('#vehicle_make').val(res.data.make);
                            $('#vehicle_model').val(res.data.model);
                            $('#vehicle_plate').val(res.data.plate_number);
                            $('#vehicle_color').val(res.data.color);

                            // Transmission
                            if (res.data.transmission_type !== 'Automatic' && res.data.transmission_type !== 'Manual') {
                                $('#vehicle_transmission').val('Other');
                                $('#vehicle_transmission_other').show().val(res.data.transmission_type);
                            } else {
                                $('#vehicle_transmission').val(res.data.transmission_type);
                                $('#vehicle_transmission_other').hide().val('');
                            }

                            // Fuel
                            if (['Gasoline', 'Diesel', 'Electric', 'Hybrid'].indexOf(res.data.fuel_type) === -1) {
                                $('#vehicle_fuel').val('Other');
                                $('#vehicle_fuel_other').show().val(res.data.fuel_type);
                            } else {
                                $('#vehicle_fuel').val(res.data.fuel_type);
                                $('#vehicle_fuel_other').hide().val('');
                            }

                            $('#editVehicleModal').modal('show');
                        } else {
                            Swal.fire('Error', res.message || 'Failed to fetch vehicle info.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Server error while fetching vehicle info.', 'error');
                    }
                });
            });

            // Save changes with validation
            $('#saveVehicleBtn').on('click', function() {
                let vehicleId = $('#vehicle_id').val();
                let make = $('#vehicle_make').val().trim();
                let model = $('#vehicle_model').val().trim();
                let plate = $('#vehicle_plate').val().trim();
                let color = $('#vehicle_color').val().trim();


                let transmission = $('#vehicle_transmission').val();
                if (transmission === 'Other') transmission = $('#vehicle_transmission_other').val().trim();

                let fuel = $('#vehicle_fuel').val();
                if (fuel === 'Other') fuel = $('#vehicle_fuel_other').val().trim();

                // Validate
                if (!make || !model || !transmission || !fuel) {
                    Swal.fire('Validation', 'missing required fields.', 'warning');
                    return;
                }
                console.log(vehicleId, make, model, plate, color, transmission, fuel);

                $.ajax({
                    url: '/CABTECH/CAPSTONE - WEBSITE/handlers/manageAccount.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'updateVehicle',
                        vehicle_id: vehicleId,
                        make: make,
                        model: model,
                        plate_number: plate,
                        color: color,
                        transmission_type: transmission,
                        fuel_type: fuel
                    },
                    success: function(res) {
                        if (res.success) {
                            Swal.fire('Success', res.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', res.message || 'Failed to update vehicle.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Server error while updating vehicle.', 'error');
                    }
                });
            });

            // Delete vehicle
            $(document).on('click', '.delete-vehicle', function() {
                let vehicleId = $(this).data('vehicle-id');

                Swal.fire({
                    title: 'Are you sure?',
                    text: 'This vehicle will be permanently removed.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes!',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '/CABTECH/CAPSTONE - WEBSITE/handlers/manageAccount.php',
                            method: 'POST',
                            dataType: 'json',
                            data: {
                                action: 'removeVehicle',
                                vehicle_id: vehicleId
                            },
                            success: function(res) {
                                if (res.success) {
                                    Swal.fire('Deleted!', res.message, 'success').then(() => location.reload());
                                } else {
                                    Swal.fire('Error', res.message || 'Failed to delete vehicle.', 'error');
                                }
                            },
                            error: function() {
                                Swal.fire('Error', 'Server error while deleting vehicle.', 'error');
                            }
                        });
                    }
                });
            });































            let originalUsername = $('#username').val();

            // Edit button
            $('#editAccountBtn').on('click', function() {
                $('#username').prop('readonly', false);
                $('#passwordFields, .action-buttons').show();
                $(this).hide();
            });

            // Cancel button
            $('#cancelAccountBtn').on('click', function() {
                $('#username').val(originalUsername).prop('readonly', true);
                $('#current_password, #new_password, #confirm_new_password').val('');
                $('#passwordFields, .action-buttons').hide();
                $('#editAccountBtn').show();
            });

            // Save changes
            $('#saveAccountBtn').on('click', function() {
                let username = $('#username').val().trim();
                let currentPw = $('#current_password').val();
                let newPw = $('#new_password').val();
                let confirmPw = $('#confirm_new_password').val();
                console.log(username);

                // Username validation
                const usernamePattern = /^[a-zA-Z0-9_-]{6,}$/;
                if (!usernamePattern.test(username)) {
                    Swal.fire('Validation', 'Username must be at least 6 characters and can only contain letters, numbers, - and _.', 'warning');
                    return;
                }

                // Password validation if changing password
                if (currentPw || newPw || confirmPw) {
                    if (!currentPw || !newPw || !confirmPw) {
                        Swal.fire('Validation', 'All password fields are required if changing password.', 'warning');
                        return;
                    }

                    if (newPw !== confirmPw) {
                        Swal.fire('Validation', 'New password and confirmation do not match.', 'warning');
                        return;
                    }

                    const passwordPattern = /^[a-zA-Z0-9_-]{6,}$/;
                    if (!passwordPattern.test(newPw)) {
                        Swal.fire('Validation', 'Password must be at least 6 characters and can only contain letters, numbers, - and _.', 'warning');
                        return;
                    }
                }

                // AJAX
                $.ajax({
                    url: '/CABTECH/CAPSTONE - WEBSITE/handlers/manageAccount.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'updateAccount',
                        username: username,
                        current_password: currentPw,
                        new_password: newPw
                    },
                    success: function(res) {

                        if (res.success) {
                            Swal.fire('Success', 'Account updated successfully!', 'success');
                            $('#username').val(res.username);
                            $('#username').prop('readonly', true);
                            $('#passwordFields, .action-buttons').hide();
                            $('#editAccountBtn').show();
                            $('#current_password, #new_password, #confirm_new_password').val('');
                            // Update the originalUsername so cancel works correctly
                            originalUsername = res.username;
                        } else {
                            Swal.fire('Error', res.message || 'Failed to update account.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Server error. Try again later.', 'error');
                    }
                });
            });












            // Toggle password visibility
            $('.toggle-pw').on('click', function() {
                const $btn = $(this);
                const $input = $btn.siblings('input');

                if ($input.attr('type') === 'password') {
                    $input.attr('type', 'text');
                    $btn.find('i').removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    $input.attr('type', 'password');
                    $btn.find('i').removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });











            // Store original values for cancel
            let originalData = {};

            // When Edit Profile is clicked
            $('#editProfileBtn').on('click', function() {
                // Save original values
                $('.form-group input').each(function() {
                    originalData[this.id] = $(this).val();
                });

                // Make inputs editable
                $('.form-group input').prop('readonly', false);

                // Toggle buttons
                $(this).hide();
                $('#saveProfileBtn, #cancelProfileBtn').show();
            });

            // When Cancel is clicked
            $('#cancelProfileBtn').on('click', function() {
                // Restore original values
                $('.form-group input').each(function() {
                    const id = this.id;
                    if (originalData[id] !== undefined) {
                        $(this).val(originalData[id]);
                    }
                });

                // Make inputs readonly again
                $('.form-group input').prop('readonly', true);

                // Toggle buttons
                $('#saveProfileBtn, #cancelProfileBtn').hide();
                $('#editProfileBtn').show();
            });




            // helper: small email mask (same idea you used)
            function maskEmail(email) {
                if (!email) return "";
                const parts = email.split("@");
                if (parts.length !== 2) return email;
                const name = parts[0];
                const domain = parts[1];
                const visible = name.length <= 2 ? name : name[0] + "..." + name[name.length - 1];
                return visible + "@" + domain;
            }

            // Save via AJAX (updated to require verification when email changed)
            $('#saveProfileBtn').on('click', function() {
                let valid = true; // flag for validation
                let formData = {};
                $('.form-group input').each(function() {
                    formData[this.id] = $(this).val();
                });

                // Email validation
                const email = (formData.email || '').trim();
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test(email)) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Invalid Email',
                        text: 'Please enter a valid email address.'
                    });
                    valid = false;
                    return;
                }

                // Phone number validation (allow numbers, +, -, spaces)
                const phone = (formData.contact_number || '').trim();
                const phonePattern = /^[0-9+\-\s]{7,15}$/;
                if (!phonePattern.test(phone)) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Invalid Phone Number',
                        text: 'Please enter a valid phone number. Should start with 09 and be 11 digits'
                    });
                    valid = false;
                    return;
                }

                if (!valid) return; // stop if validation fails

                // If email changed compared to originalData.email -> require verification
                const origEmail = (originalData.email || '').trim();
                const emailChanged = origEmail.toLowerCase() !== email.toLowerCase();

                if (emailChanged) {
                    // send verification email to the new email
                    Swal.fire({
                        title: 'Sending verification code...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });

                    $.ajax({
                        url: '/CABTECH/CAPSTONE - WEBSITE/handlers/send_verification_email.php',
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            email: email,
                            allow_existing: 0 // <-- dont allow sending if email exists
                        },
                        success: function(sendRes) {
                            Swal.close();
                            if (!sendRes || !sendRes.success) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Failed to send code',
                                    text: (sendRes && sendRes.message) ? sendRes.message : 'Could not send verification email.'
                                });
                                return;
                            }

                            // ask user to enter the code
                            Swal.fire({
                                title: 'Enter verification code',
                                html: `<p>We've sent a code to <strong>${maskEmail(email)}</strong>. Enter it below.</p>
                                <input id="verifyCodeInput" class="swal2-input" placeholder="6-digit code">`,
                                focusConfirm: false,
                                showCancelButton: true,
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                confirmButtonText: 'Verify',
                                preConfirm: () => {
                                    const code = $('#verifyCodeInput').val() || '';
                                    if (!code.trim()) {
                                        Swal.showValidationMessage('Please enter the verification code.');
                                        return false;
                                    }
                                    return code.trim();
                                }
                            }).then((swRes) => {
                                if (!swRes.isConfirmed) {
                                    // user cancelled entering code
                                    Swal.fire({
                                        icon: 'info',
                                        title: 'Verification cancelled',
                                        text: 'Email change was not verified. Profile not updated.'
                                    });
                                    return;
                                }

                                const code = swRes.value;
                                Swal.fire({
                                    title: 'Verifying code...',
                                    allowOutsideClick: false,
                                    didOpen: () => Swal.showLoading()
                                });

                                $.ajax({
                                    url: '/CABTECH/CAPSTONE - WEBSITE/handlers/verify_email_code.php',
                                    method: 'POST',
                                    dataType: 'json',
                                    data: {
                                        email: email,
                                        code: code
                                    },
                                    success: function(vRes) {
                                        Swal.close();
                                        if (!vRes || !vRes.success) {
                                            Swal.fire({
                                                icon: 'error',
                                                title: 'Invalid Code',
                                                text: (vRes && vRes.message) ? vRes.message : 'The verification code is invalid.'
                                            });
                                            return;
                                        }

                                        // verification succeeded -> proceed to update profile
                                        updateProfile(formData);
                                    },
                                    error: function(xhr, status, err) {
                                        Swal.close();
                                        console.error("verify_email_code AJAX error:", status, err, xhr && xhr.responseText);
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Server Error',
                                            text: 'There was a problem verifying your code. Try again later.'
                                        });
                                    }
                                });
                            });
                        },
                        error: function(xhr, status, err) {
                            Swal.close();
                            console.error("send_verification_email AJAX error:", status, err, xhr && xhr.responseText);
                            Swal.fire({
                                icon: 'error',
                                title: 'Server Error',
                                text: 'There was a problem sending the verification email. Try again later.'
                            });
                        }
                    });

                } else {
                    // email not changed -> directly update profile
                    updateProfile(formData);
                }
            });

            // Function to actually update profile (same as earlier)
            function updateProfile(formData) {
                formData.action = 'updateProfile';
                $.ajax({
                    url: '/CABTECH/CAPSTONE - WEBSITE/handlers/manageAccount.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Profile Updated',
                                text: 'Your profile has been updated successfully!',
                                timer: 2000,
                                showConfirmButton: false
                            });
                            $('.form-group input').prop('readonly', true);
                            $('#saveProfileBtn, #cancelProfileBtn').hide();
                            $('#editProfileBtn').show();

                            // update originalData so further edits compare correctly
                            $('.form-group input').each(function() {
                                originalData[this.id] = $(this).val();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Failed to update profile.'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'An unexpected error occurred.'
                        });
                    }
                });
            }

        });
    </script>
</body>

</html>