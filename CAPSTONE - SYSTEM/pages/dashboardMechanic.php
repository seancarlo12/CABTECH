<?php
session_start();
include_once '../config/db.php';


// ALLOW ACCES IF AJAX REQUEST AND POSTS
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // ALLOW ACCES IF AJAX REQUEST ONLY
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        include 'denyAccess.html';
        exit;
    }
}


if (isset($_GET['action']) && $_GET['action'] == "inProgressServices") {

    $user_id = intval($_GET['user_id'] ?? 0);

    $sql = "
    SELECT 
        r.record_id,
        r.started_dt,
        r.completion_dt,
        r.record_status,
        am.user_id AS mechanic_id,
        u.first_name AS mechanic_name,
        req.request_id,
        v.make, v.model, v.plate_number,
        GROUP_CONCAT(
            COALESCE(s.service_name, rs.custom_service)
            SEPARATOR ', '
        ) AS services
    FROM recordstbl r
    INNER JOIN assigned_mechanicstbl am ON r.record_id = am.record_id
    INNER JOIN userstbl u ON am.user_id = u.user_id
    INNER JOIN requeststbl req ON r.request_id = req.request_id
    INNER JOIN vehiclestbl v ON req.vehicle_id = v.vehicle_id
    LEFT JOIN requested_servicestbl rs ON req.request_id = rs.request_id
    LEFT JOIN servicestbl s ON rs.service_id = s.service_id
    WHERE am.user_id = ? AND r.record_status = 'In Progress'
    GROUP BY r.record_id
    ORDER BY r.started_dt DESC
    ";

    // Prepare statement
    $stmt = $db_connection->prepare($sql);
    $stmt->bind_param("i", $user_id); // "i" = integer
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        // Debug SQL error
        echo json_encode([
            "error" => "SQL Error",
            "query" => $sql,
            "message" => $db_connection->error
        ]);
        exit;
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    if (empty($rows)) {
        echo json_encode([]); // return empty array
        exit;
    }

    // Success: return the records
    echo json_encode($rows);
    exit;
}

$response = [];

if (isset($_SESSION['User_role']) && $_SESSION['User_role'] == "Mechanic") {
    $mechanicId = (int)$_SESSION['User_id']; // safety cast

    // ✅ Records grouped by status for this mechanic
    $sqlRecords = "
        SELECT 
            COUNT(DISTINCT CASE WHEN r.record_status = 'In Progress' THEN r.record_id END) AS assigned_count,
            COUNT(DISTINCT CASE WHEN r.record_status IN ('Completed', 'Invoice Issued') THEN r.record_id END) AS completed_count
        FROM recordstbl r
        INNER JOIN assigned_mechanicstbl am 
            ON r.record_id = am.record_id
        WHERE am.user_id = ?;
    ";

    $stmt = mysqli_prepare($db_connection, $sqlRecords);
    mysqli_stmt_bind_param($stmt, "i", $mechanicId);
    mysqli_stmt_execute($stmt);
    $resRecords = mysqli_stmt_get_result($stmt);

    if ($resRecords) {
        $rowRecords = mysqli_fetch_assoc($resRecords);
        $response['records'] = [
            'assigned'  => (int)$rowRecords['assigned_count'],
            'completed' => (int)$rowRecords['completed_count']
        ];
    }

    // ✅ Mechanic profile info (grouped under "user")
    $sqlUser = "
        SELECT user_id, first_name, middle_name, last_name, contact_number, address, email, role, work_status
        FROM userstbl 
        WHERE user_id = ?
        LIMIT 1
    ";
    $stmtUser = mysqli_prepare($db_connection, $sqlUser);
    mysqli_stmt_bind_param($stmtUser, "i", $mechanicId);
    mysqli_stmt_execute($stmtUser);
    $resUser = mysqli_stmt_get_result($stmtUser);

    if ($resUser) {
        $rowUser = mysqli_fetch_assoc($resUser);
        $response['user'] = $rowUser; // put everything inside "user"
    }


    $sql = "SELECT 
                s.specialty_id, 
                s.name
            FROM mechanic_specialtiestbl ms
            INNER JOIN specialtiestbl s 
                ON ms.specialty_id = s.specialty_id
            WHERE ms.user_id = ?";

    $stmt = $db_connection->prepare($sql);
    $stmt->bind_param("i", $mechanicId);
    $stmt->execute();
    $result = $stmt->get_result();

    $specialties = [];
    while ($row = $result->fetch_assoc()) {
        $specialties[] = [
            "specialty_id"   => $row["specialty_id"],
            "specialty_name" => $row["name"]
        ];
    }

    // ✅ put everything inside "user" once, after the loop
    $response['user']['specialties'] = $specialties;
}




if (isset($_POST['action']) && $_POST['action'] === 'updateMechanicProfile' && isset($_POST['user_data'])) {
    $response = [];
    $userData = $_POST['user_data'];

    // Make sure user_id exists
    if (!isset($userData['user_id'])) {
        $response['status'] = 'error';
        $response['message'] = 'User ID is required.';

        echo json_encode($response);
        exit;
    }

    $userId = intval($userData['user_id']);
    unset($userData['user_id']); // Remove it from fields to be updated

    if (empty($userData)) {
        $response['status'] = 'error';
        $response['message'] = 'No changes were submitted.';

        echo json_encode($response);
        exit;
    }

    // DUPLICATE CHECK SECTION (IMPORTANT!)
    $mobile = $userData['contact_number'] ?? null;
    $email = $userData['email'] ?? null;

    if ($mobile || $email) {
        $checkQuery = "SELECT * FROM userstbl 
                        WHERE (contact_number = ? OR email = ?) 
                        AND user_id != ?";
        $checkStmt = $db_connection->prepare($checkQuery);
        $checkStmt->bind_param("ssi", $mobile, $email, $userId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result && $result->num_rows > 0) {
            $response['status'] = 'existing';
            $response['message'] = 'Duplicate contact number or email found.';

            echo json_encode($response);
            exit;
        }
        $checkStmt->close();
    }

    // Proceed with update
    $setClauses = [];
    $params = [];
    $types = '';

    foreach ($userData as $column => $value) {
        $setClauses[] = "$column = ?";
        $params[] = $value;
        $types .= 's'; // assuming all values are strings
    }

    $query = "UPDATE userstbl SET " . implode(", ", $setClauses) . " WHERE user_id = ?";
    $params[] = $userId;
    $types .= 'i';

    $stmt = $db_connection->prepare($query);
    if ($stmt === false) {
        $response['status'] = 'error';
        $response['message'] = 'Failed to prepare the SQL statement.';

        echo json_encode($response);
        exit;
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $response['status'] = 'success';
        $response['message'] = 'User information successfully updated.';
    } else {
        $response['status'] = 'error';
        $response['message'] = 'No changes made or user does not exist.';
    }


    echo json_encode($response);
    exit;
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mechanic Dashboard</title>
    <link rel="stylesheet" href="style/dashboardMechanic.css">


    <script>
        var dashboardStats = <?php echo json_encode($response); ?>;
        // Example: dashboardStats object from PHP
        console.log("Dashboard Stats:", dashboardStats);


        // ✅ Insert revenue (grand_total)
        if (dashboardStats) {

            // Users count
            document.querySelector(".com-ser span").textContent = dashboardStats.records.completed;
            document.querySelector(".ass-ser span").textContent = dashboardStats.records.assigned;

            // ✅ Fill profile info
            $(".profile-fullname").text(
                `${dashboardStats.user.last_name}, ${dashboardStats.user.first_name}` +
                (dashboardStats.user.middle_name ? `, ${dashboardStats.user.middle_name}` : "")
            );

            $(".profile-contact").text(dashboardStats.user.contact_number);
            $(".profile-address").text(dashboardStats.user.address);
            $(".profile-email").text(dashboardStats.user.email);

            let specialtiesHtml = dashboardStats.user.specialties
                .map(s => s.specialty_name)
                .join(", ");

            document.querySelector(".profile-info").innerHTML += `
                <p><b>Specialties:</b> 
                <span class="specialties-text">
                ${specialtiesHtml}s
                </span>
                </p>
            `;


            const user = dashboardStats.user; // your single user object
            const userId = user.user_id;
            const status = user.work_status; // "In" or "Out"

            const html = `
            <div class="radio-group btn-toggle-work" data-userid="${userId}" data-status="${status}">
                <input type="radio" id="in" name="status" value="in" ${status === "In" ? "checked" : ""}>
                <label for="in">In</label>

                <input type="radio" id="out" name="status" value="out" ${status === "Out" ? "checked" : ""}>
                <label for="out">Out</label>
            </div>`;

            // append it to some container
            document.getElementById('user-toggle-container').innerHTML = html;
        }

        function updateDateTime() {
            const now = new Date();
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            document.getElementById('date-time').textContent =
                now.toLocaleDateString('en-US', options);
        }
        // update immediately and every minute
        updateDateTime();
        setInterval(updateDateTime, 60000);
    </script>
</head>

<body>
    <div id="content">
        <p class="content-id"><i class='bx bx-circle'></i> <span>dashboard - mechanic</span></p>


        <div style="display: flex; justify-content: center; align-items: center; height: 80vh;">
            <div class="dashboard">
                <!-- Left Column -->
                <div class="card welcome">
                    <h3 class="box-head">QUICK ACCESS - WELCOME, <?php echo htmlspecialchars($_SESSION["Username"]); ?>! 👋</h3>
                    <div class="inside-box">
                        <p id="date-time"></p>
                        <div class="quick-access">
                            <button onclick="window.open('index.php', '_self')">
                                Visit Website <i class='bx bx-right-arrow-alt'></i>
                            </button>
                            <button onclick="loadPage('pages/serviceRecords.php')">
                                Manage Assigned Services <i class='bx bx-right-arrow-alt'></i>
                            </button>
                            <button onclick="loadPage('pages/shopCalendar.php')">
                                View Calendar <i class='bx bx-right-arrow-alt'></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="quick-stats card">
                    <h3 class="box-head">QUICK STATS</h3>
                    <div class="inside-box">
                        <div class="com-ser stat">
                            <i class='bx bx-receipt'></i>
                            <span></span>
                            <p>Your Completed Services</p>
                        </div>
                        <div class="ass-ser stat">
                            <i class='bx bx-user-pin'></i>
                            <span></span>
                            <p>Assigned Services</p>
                        </div>
                    </div>
                </div>

                <div class="card wor-sta">
                    <h3 class="box-head">Work Status</h3>
                    <div class="inside-box" id="user-toggle-container">
                    </div>
                </div>

                <div class="card profile">
                    <h3 class="box-head">Your Profile</h3>
                    <div class="inside-box">

                        <!-- Profile Info -->
                        <div class="profile-info">
                            <p><b>Full Name:</b> <span class="profile-fullname"></span></p>
                            <p><b>Contact Number:</b> <span class="profile-contact"></span></p>
                            <p><b>Address:</b> <span class="profile-address"></span></p>
                            <p><b>Email:</b> <span class="profile-email"></span></p>

                        </div>

                        <div class="pf-buttons">

                            <button id="editProfile"><i class="bx bx-pencil"></i>Edit Your Profile</button>
                            <button id="manageMechSpecialty"><i class="bx bx-cog"></i>Manage Specialties</button>
                        </div>
                    </div>
                </div>


                <div class="card assigned">
                    <h3 class="box-head">Assigned Services (In Progress)</h3>
                    <div class="inside-box" id="displayInProgress">
                    </div>
                </div>

            </div>
        </div>

        <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="editUserModal" aria-hidden="true" aria-labelledby="editUserLabel" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="editUserLabel">Edit Your Information</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1" onclick="resetEditForm();"></button>
                    </div>
                    <div class="modal-body">
                        <form action="" id="editUserForm" method="POST" onsubmit="return validateUpdatedUserForm();">
                            <label for="editFirstName">First Name</label>
                            <input class="form-control" type="text" id="editFirstName" name="first_name" placeholder="Enter User's First Name" required>

                            <label for="editLastName">Last Name</label>
                            <input class="form-control" type="text" id="editLastName" name="last_name" placeholder="Enter User's Last Name" required>

                            <label for="editMiddleName">Middle Name</label>
                            <input type="checkbox" id="enableEditMiddleName" checked>
                            <input class="form-control" type="text" id="editMiddleName" name="middle_name" placeholder="Enter User's Middle Name">

                            <label for="editMobile">Mobile Number</label>
                            <input oninput="validateMobile(this);" value="09" class="form-control" type="tel" id="editMobile" name="mobile_number" placeholder="Enter User's Mobile Number" maxlength="11" required>

                            <label for="editEmail">Email</label>
                            <input class="form-control" type="email" id="editEmail" name="email" placeholder="Enter User's Email">

                            <label for="editAddress">Address</label>
                            <input class="form-control" type="text" id="editAddress" name="address" placeholder="Enter User's Address">
                            <div class="form-text">
                                Please enter the address in the format: <strong>House Number, Barangay, City</strong> (e.g., 123 Purok 1, Barangay Sampaguita, Cabanatuan City).
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="resetEditForm();">Cancel</button>
                                <button type="submit" class="btn btn-primary"> <i class="bx bx-save"></i>Update Record</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>






        <div data-bs-keyboard="false" class="modal fade static" data-bs-backdrop="static" id="manageMechSpecialtyModal" aria-hidden="true" aria-labelledby="exampleModalToggleLabel" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="exampleModalToggleLabel">manage mechanic specialties</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <!-- Left: all specialties (checkbox list) -->
                            <div id="all-specialties" class="col-6">
                                <h6>All Specialties</h6>
                                <input type="text" id="specialtySearch" class="form-control mb-2" placeholder="Search specialties...">
                                <div id="allSpecialtiesList" style="max-height: 320px; overflow:auto; border:1px solid #e9ecef; padding:8px; border-radius:6px;">
                                    <!-- populated by JS -->
                                    Loading...
                                </div>
                            </div>

                            <!-- Right: selected specialties -->
                            <div class="col-6">
                                <h6>Mechanics Specialties</h6>
                                <ul id="selectedSpecialtiesList" style="list-style:none; padding-left:0; max-height:320px; overflow:auto; border:1px solid #e9ecef; padding:8px; border-radius:6px;">
                                    <!-- populated by JS -->
                                </ul>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Back</button>
                            <button id="confirmSpecialty" class="btn btn-primary"> <i class="bx bx-plus"></i>Confirm Specialties</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>




    </div>
    <script>
        let user = dashboardStats.user; // Store original user data

        const modifiedData = {};

        function validateMobile(input) {
            let value = input.value;

            // Remove non-digit characters
            value = value.replace(/\D/g, '');

            // Enforce it starts with "09"
            if (!value.startsWith("09")) {
                if (value.length >= 2) {
                    value = "09" + value.slice(2);
                } else {
                    value = "09";
                }
            }

            // Limit to 11 digits
            value = value.slice(0, 11);

            input.value = value;
        }






        $(document).on('click', '#editProfile', function(e) {
            e.preventDefault();

            $('#editFirstName').val(user.first_name);
            $('#editLastName').val(user.last_name);
            $('#editMiddleName').val(user.middle_name);
            $('#editMobile').val(user.contact_number);
            $('#editEmail').val(user.email);
            $('#editAddress').val(user.address);


            if (!user.middle_name) {
                $('#enableEditMiddleName').prop('checked', false);
                $('#editMiddleName').prop('disabled', true).val('');
            } else {
                $('#enableEditMiddleName').prop('checked', true);
                $('#editMiddleName').prop('disabled', false);
            }


            $('#editUserModal').modal('show');

        });


        document.getElementById('enableEditMiddleName').addEventListener('change', function() {
            document.getElementById('editMiddleName').disabled = !this.checked;
            document.getElementById('editMiddleName').value = '';
        });


        function clearEditInputs() {
            $('#editFirstName, #editLastName, #editMiddleName, #editMobile, #editEmail, #editAddress').val('');
            $('#enableEditMiddleName').prop('checked', true);
            // user = null;
        }



        function validateUpdatedUserForm() {
            //  Clear all previous keys while keeping the same object reference
            for (let key in modifiedData) {
                delete modifiedData[key];
            }
            const swalOptions = {
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (popup) => {

                    Swal.getConfirmButton().focus();

                    // Pause timer when hovering over modal
                    popup.addEventListener('mouseenter', Swal.stopTimer);
                    popup.addEventListener('mouseleave', Swal.resumeTimer);
                }
            };

            const firstName = document.getElementById('editFirstName').value;
            const lastName = document.getElementById('editLastName').value;
            const middleName = document.getElementById('editMiddleName').value;
            const mobile = document.getElementById('editMobile').value;
            const email = document.getElementById('editEmail').value;
            const address = document.getElementById('editAddress').value;

            const normalize = (val) => (val || "").trim();

            const mobilePattern = /^09\d{9}$/;

            if (!mobilePattern.test(mobile)) {
                $('#editUserModal').modal('hide');
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class=\'bx bx-error-circle\'></i>',
                    title: 'Invalid Mobile Number',
                    text: 'Mobile number must start with 09 and be 11 digits long (e.g., 09123456789).'
                }).then(() => {
                    // Show modal again after Swal closes
                    $('#editUserModal').modal('show');
                });
                return false;
            }

            if (normalize(firstName) !== normalize(user.first_name)) {
                modifiedData.first_name = firstName;
            }
            if (normalize(lastName) !== normalize(user.last_name)) {
                modifiedData.last_name = lastName;
            }
            if (normalize(middleName) !== normalize(user.middle_name)) {
                modifiedData.middle_name = middleName;
            }
            if (normalize(mobile) !== normalize(user.contact_number)) {
                modifiedData.contact_number = mobile;
            }
            if (normalize(email) !== normalize(user.email)) {
                modifiedData.email = email;
            }
            if (normalize(address) !== normalize(user.address)) {
                modifiedData.address = address;
            }

            if (Object.keys(modifiedData).length === 0) {
                $('#editUserModal').modal('hide');
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-info-circle"></i>',
                    title: 'No Changes Made',
                    text: 'Make changes before saving.'
                }).then(() => {
                    $('#editUserModal').modal('show');
                });
                return false;
            }

            // Include the user's ID or identifier for update
            modifiedData.user_id = user.user_id;


            updateUserRecord(modifiedData);
            return false;
        }

        function updateUserRecord(updatedUserData) {
            const swalOptions = {
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                didOpen: (popup) => {

                    Swal.getConfirmButton().focus();

                    // Pause timer when hovering over modal
                    popup.addEventListener('mouseenter', Swal.stopTimer);
                    popup.addEventListener('mouseleave', Swal.resumeTimer);
                }
            };


            $.ajax({
                url: 'pages/dashboardMechanic.php',
                method: 'POST',
                dataType: 'json', // Important: expecting JSON from PHP
                data: {
                    user_data: updatedUserData,
                    action: 'updateMechanicProfile'
                },
                success: function(response) {
                    if (response.status === 'success') {
                        $('#editUserModal').modal('hide');
                        Swal.fire({
                            ...swalOptions,
                            iconHtml: '<i class="bx bx-check-circle"></i>',
                            title: 'User Updated Successfully',
                            text: response.message
                        });

                        console.log(updatedUserData);
                        // Update name
                        if (modifiedData.last_name || modifiedData.first_name || modifiedData.middle_name) {
                            const fullLast = modifiedData.last_name ?? user.last_name;
                            const fullFirst = modifiedData.first_name ?? user.first_name;
                            const fullMiddle = modifiedData.middle_name !== undefined ? modifiedData.middle_name : user.middle_name;

                            $(".profile-fullname").text(`${fullLast}, ${fullFirst}` + (fullMiddle ? `, ${fullMiddle}` : ''));

                            if (modifiedData.first_name) user.first_name = modifiedData.first_name;
                            if (modifiedData.last_name) user.last_name = modifiedData.last_name;
                            if (modifiedData.middle_name !== undefined) user.middle_name = modifiedData.middle_name;
                        }

                        // Update contact
                        if (modifiedData.contact_number) {
                            $(".profile-contact").text(modifiedData.contact_number);
                            user.contact_number = modifiedData.contact_number;
                        }

                        // Update email
                        if (modifiedData.email) {
                            $(".profile-email").text(modifiedData.email);
                            user.email = modifiedData.email;
                        }

                        // Update address
                        if (modifiedData.address) {
                            $(".profile-address").text(modifiedData.address);
                            user.address = modifiedData.address;
                        }


                    } else if (response.status === 'existing') {
                        $('#editUserModal').modal('hide');
                        Swal.fire({
                            ...swalOptions,
                            iconHtml: '<i class="bx bx-x-circle"></i>',
                            title: 'Duplicate Record',
                            text: response.message
                        }).then(() => {
                            $('#editUserModal').modal('show');
                        });

                    } else {
                        $('#editUserModal').modal('hide');
                        Swal.fire({
                            ...swalOptions,
                            iconHtml: '<i class="bx bx-x-circle"></i>',
                            title: 'Update Failed',
                            text: response.message
                        }).then(() => {
                            $('#editUserModal').modal('show');
                        });
                    }
                },
                error: function(xhr, status, error) {
                    $('#editUserModal').modal('hide');
                    Swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-x-circle"></i>',
                        title: 'Error',
                        text: 'Something went wrong while updating the user.'
                    }).then(() => {
                        $('#editUserModal').modal('show');
                    });
                    console.error("AJAX Error:", status, error);
                }
            });
        }











        function resetEditForm() {

            const firstName = document.getElementById('editFirstName').value;
            const lastName = document.getElementById('editLastName').value;
            const middleName = document.getElementById('editMiddleName').value;
            const mobile = document.getElementById('editMobile').value;
            const email = document.getElementById('editEmail').value;
            const address = document.getElementById('editAddress').value;

            const normalize = (val) => (val || "").trim();

            if (
                normalize(firstName) !== normalize(user.first_name) ||
                normalize(lastName) !== normalize(user.last_name) ||
                normalize(middleName) !== normalize(user.middle_name) ||
                normalize(mobile) !== normalize(user.contact_number) ||
                normalize(email) !== normalize(user.email) ||
                normalize(address) !== normalize(user.address)) {
                Swal.fire({
                    title: 'Discard Changes?',
                    html: 'Are you sure you want discard changes you made?',
                    iconHtml: '<i class="bx bx-info-circle"></i>',
                    showCancelButton: true,
                    cancelButtonText: 'Keep Editing',
                    confirmButtonText: 'Discard',
                    allowOutsideClick: false,
                    focusConfirm: true,
                }).then((result) => {
                    if (result.isConfirmed) {
                        clearEditInputs();
                    } else {
                        $('#editUserModal').modal('show');
                    }
                });
            } else {
                clearEditInputs();
            }

        }


        const swalOptions = {
            timer: 4000,
            timerProgressBar: true,
            showConfirmButton: false,
            allowEscapeKey: false,
            didOpen: (popup) => {

                Swal.getConfirmButton().focus();

                // Pause timer when hovering over modal
                popup.addEventListener('mouseenter', Swal.stopTimer);
                popup.addEventListener('mouseleave', Swal.resumeTimer);
            }
        };

        $(document).on('click', '.btn-toggle-work', function(e) {
            e.preventDefault();
            const group = $(this);
            let current = group.data('status'); // "In" or "Out"
            const newStatus = current.toLowerCase() === 'in' ? 'Out' : 'In';

            // Update data-status immediately for correct toggling on next click
            group.data('status', newStatus);

            // optionally, visually toggle radio immediately or wait for success
            group.find('input[value="in"]').prop('checked', newStatus === 'In');
            group.find('input[value="out"]').prop('checked', newStatus === 'Out');

            // group.prop('disabled', true);

            $.ajax({
                url: 'handlers/manageUsers-handler.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    user_id: group.data('userid'),
                    work_status: newStatus,
                    action: 'updateWorkStatus'
                },
                success: function(resp) {
                    if (typeof resp === 'string') {
                        try {
                            resp = JSON.parse(resp);
                        } catch (e) {
                            alert('Invalid response');
                            return;
                        }
                    }
                    if (!resp.success) {
                        alert(resp.message || 'Update failed');
                        // rollback visual and data-status if failed
                        group.data('status', current);
                        group.find('input[value="in"]').prop('checked', current === 'In');
                        group.find('input[value="out"]').prop('checked', current === 'Out');

                    }

                    swal.fire({
                        ...swalOptions,
                        iconHtml: '<i class="bx bx-check-circle"></i>',
                        title: 'Work Status Updated',
                        text: 'Succesfully updated your work status!'

                    });
                },
                error: function() {
                    // rollback on network error
                    group.data('status', current);
                    group.find('input[value="in"]').prop('checked', current === 'In');
                    group.find('input[value="out"]').prop('checked', current === 'Out');
                    alert('Request failed — check your network or server.');
                },
                complete: function() {
                    group.prop('disabled', false);
                }
            });
        });







        $(document).on('click', '#manageMechSpecialty', function() {
            $("#specialtySearch").val('');
            $.ajax({
                url: 'handlers/manageUsers-handler.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'getMechanicsSpecialty',
                    user_id: user.user_id
                },
                success: function(response) {
                    console.log(response);
                    if (response.status === 'success') {
                        let specialties = response.allSpecialties; // <-- using backend key 'all'
                        let assigned = response.assignedSpecialties.map(s => parseInt(s.specialty_id)); // get assigned IDs as array
                        let listHtml = '';

                        if (specialties.length > 0) {
                            specialties.forEach(spec => {
                                let isChecked = assigned.includes(parseInt(spec.specialty_id)) ? 'checked' : '';

                                listHtml += `
                                <div class="form-check">
                                    <input class="form-check-input specialty-checkbox" 
                                        type="checkbox" 
                                        value="${spec.specialty_id}" 
                                        id="spec_${spec.specialty_id}" ${isChecked}>
                                    <label class="form-check-label" for="spec_${spec.specialty_id}">
                                        ${spec.name}
                                    </label>
                                </div>
                            `;
                            });
                        } else {
                            listHtml = '<p>No specialties available.</p>';
                        }

                        // Place checkboxes in left panel
                        $('#allSpecialtiesList').html(listHtml);

                        // Build selected list from assigned
                        if (assigned.length > 0) {
                            let selectedHtml = '<ul>';
                            specialties.forEach(spec => {
                                if (assigned.includes(parseInt(spec.specialty_id))) {
                                    selectedHtml += `<li>${spec.name}</li>`;
                                }
                            });
                            selectedHtml += '</ul>';
                            $('#selectedSpecialtiesList').html(selectedHtml);
                        } else {
                            $('#selectedSpecialtiesList').html('<p>No specialties selected.</p>');
                        }

                        // Show modal
                        $('#manageMechSpecialtyModal').modal('show');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Failed to fetch specialties.'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Something went wrong while loading specialties.'
                    });
                    console.error("AJAX Error:", status, error);
                }
            });



        });
        // ✅ Search functionality
        $(document).on('keyup', '#specialtySearch', function() {
            let searchVal = $(this).val().toLowerCase();

            $('#allSpecialtiesList .form-check').each(function() {
                let labelText = $(this).find('label').text().toLowerCase();
                if (labelText.includes(searchVal)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // ✅ Handle checkbox changes → update right-side list
        $(document).on('change', '.specialty-checkbox', function() {
            let selected = [];
            $('.specialty-checkbox:checked').each(function() {
                selected.push($(this).next('label').text());
            });

            if (selected.length > 0) {
                let selectedHtml = '<ul>';
                selected.forEach(name => {
                    selectedHtml += `<li>${name}</li>`;
                });
                selectedHtml += '</ul>';
                $('#selectedSpecialtiesList').html(selectedHtml);
            } else {
                $('#selectedSpecialtiesList').html('<p>No specialties selected.</p>');
            }
        });

        // Build a helper to get specialty name from checkbox id
        function getSpecialtyNameById(id) {
            // try multiple label placements
            let label = $(`#spec_${id}`).closest('label').text().trim();
            if (!label) label = $(`#spec_${id}`).next('label').text().trim();
            return label || `#${id}`;
        }
        // Confirm specialties -> save to DB
        $(document).on('click', '#confirmSpecialty', function(e) {
            e.preventDefault();

            const $btn = $(this);
            // collect selected IDs as integers and dedupe
            let selectedIds = $('.specialty-checkbox:checked').map(function() {
                return parseInt($(this).val(), 10);
            }).get().filter(n => !isNaN(n));

            selectedIds = Array.from(new Set(selectedIds)); // dedupe

            // optional: ensure we have a mechanic id
            const userId = typeof ActiveRowId !== 'undefined' ? ActiveRowId : (dashboardStats && dashboardStats.user ? dashboardStats.user.user_id : null);
            if (!userId) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Mechanic ID is missing.'
                });
                return;
            }

            // give user feedback
            $btn.prop('disabled', true).html('<i class="bx bx-loader bx-spin"></i> Saving...');

            $.ajax({
                url: 'handlers/manageUsers-handler.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'saveMechanicSpecialties',
                    user_id: userId,
                    specialties: selectedIds // jQuery serializes as specialties[]=1&specialties[]=2
                },
                success: function(response) {
                    if (response && response.status === 'success') {
                        // update UI: refresh right-side selected list
                        let selectedHtml = '';
                        if (selectedIds.length > 0) {
                            selectedIds.forEach(id => {
                                // find name by matching checkbox label
                                const label = $(`#spec_${id}`).closest('label').text().trim() || $(`#spec_${id}`).next('label').text().trim();
                                selectedHtml += `<li data-id="${id}" style="padding:6px 0; border-bottom:1px solid #f1f1f1;">${label}</li>`;
                            });
                            $('#selectedSpecialtiesList').html(selectedHtml);
                        } else {
                            $('#selectedSpecialtiesList').html('<p>No specialties selected.</p>');
                        }
                        // 2) update local dashboardStats to match server -------------------------
                        if (window.dashboardStats && dashboardStats.user) {
                            dashboardStats.user.specialties = selectedIds.map(id => ({
                                specialty_id: id,
                                name: getSpecialtyNameById(id)
                            }));
                        }

                        // 3) update the profile info area (only now, after successful save) ---
                        // create the HTML you want to display (you can use badges or comma list)
                        let profileSpecialtiesHtml = '';
                        if (selectedIds.length > 0) {
                            // e.g., show as comma-separated or badges
                            profileSpecialtiesHtml = selectedIds.map(id => `<span>${getSpecialtyNameById(id)}</span>`).join(' ');
                        } else {
                            profileSpecialtiesHtml = '<span class="text-muted">No specialties</span>';
                        }

                        // Insert or update inside .profile-info
                        const $profileInfo = $('.profile-info');
                        // find existing specialties container
                        let $specContainer = $profileInfo.find('.specialties-text');
                        if ($specContainer.length) {
                            $specContainer.html(profileSpecialtiesHtml);
                        } else {
                            // append a new line if it doesn't exist yet
                            $profileInfo.append(`
                                <p><b>Specialties:</b> 
                                    <span class="specialties-text">${profileSpecialtiesHtml}</span>
                                </p>
                            `);
                        }

                        Swal.fire({
                            ...swalOptions,
                            iconHtml: '<i class="bx bx-check-circle"></i>',
                            title: 'Mechanics Specialties Updated',
                            text: response.message || 'Specialties updated.'
                        });
                        // optionally close modal:
                        setTimeout(function() {
                            $('#manageMechSpecialtyModal').modal('hide');
                        }, 400);
                    } else {
                        Swal.fire({
                            iconHtml: '<i class="bx bx-x-circle"></i>',
                            title: 'Error Updating Mechanics Specialties',
                            text: (response && response.message) ? response.message : 'Failed to save specialties.'
                        });
                    }
                },
                error: function(xhr, status, err) {
                    Swal.fire({
                        iconHtml: '<i class="bx bx-x-circle"></i>',
                        title: 'Error Updating Mechanics Specialties',
                        text: 'Something went wrong while saving specialties.'
                    });
                    console.error('Save specialties AJAX error:', status, err, xhr.responseText);
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="bx bx-plus"></i>Confirm Specialties');
                }
            });
        });


        function loadInProgressMechanics() {
            $.get('pages/dashboardMechanic.php', {
                action: 'inProgressServices',
                user_id: user.user_id
            }, function(data) {
                const records = JSON.parse(data);
                console.log(records);
                const container = $('#displayInProgress');
                container.empty(); // clear previous content

                if (records.length === 0) {
                    container.append('<p>No Services currently In Progress for you.</p>');
                    return;
                }

                records.forEach(record => {
                    // If record.services is a string
                    const servicesList = typeof record.services === "string" ?
                        record.services.split(',').map(s => s.trim()) // split and trim spaces
                        :
                        Array.isArray(record.services) ?
                        record.services.map(s => s.service_name) :
                        [];


                    const formattedDate = new Date(record.started_dt).toLocaleString('en-US', {
                        month: 'long',
                        day: 'numeric',
                        year: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    });
                    console.log('r', servicesList);
                    container.append(`
                        <div class="service-record-card">
                            <div class="record-info">
                                <div><b>Record ID:</b> ${record.record_id}</div>
                                <div><b>Vehicle:</b> ${record.make} ${record.model} (${record.plate_number || '<span class="text-muted">Plate No. Not Provided<span>'} )</div>
                                <div><b>Started:</b> ${formattedDate}</div>
                                <div class="record-services">
                                    ${servicesList.map(service => `<span class="service-badge">${service}</span>`).join('')}
                                </div>
                            </div><button onclick="loadPage('pages/serviceRecords.php')">Manage <i class='bx bx-chevrons-right' ></i></button>
                        </div>
                    `);
                });
            });
        }

        // Load on page ready
        $(document).ready(function() {
            loadInProgressMechanics();

            // Optional: refresh every 30 seconds
            setInterval(loadInProgressMechanics, 30000);
        });
    </script>
</body>

</html>