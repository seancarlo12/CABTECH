$('#vtr-inpt').on('change', function () {
    if ($(this).val() === 'Other') {
        $('#vtr-other').removeClass('d-none');
    } else {
        $('#vtr-other').addClass('d-none').val('');
    }
});

$('#vft-inpt').on('change', function () {
    if ($(this).val() === 'Other') {
        $('#vft-other').removeClass('d-none');
    } else {
        $('#vft-other').addClass('d-none').val('');
    }
});


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


document.getElementById('enableMiddleName').addEventListener('change', function () {
    document.getElementById('middleName').disabled = !this.checked;
    document.getElementById('middleName').value = '';
});


document.getElementById('enableSelectClient').addEventListener('change', function () {
    const isChecked = this.checked;

    // Enable or disable the dropdown
    $('#selectClient').prop('disabled', !isChecked);
    $('#clientSelectNote').toggle(isChecked);

    const selectedId = $('#selectClient').val();

    if (!isChecked && selectedId) {
        // If a client was selected, clear and reset fields
        selectedClient = null;
        $('#selectClient').val('').trigger('change'); // This will clear selectedClient too

        // Clear and re-enable input fields
        $('#firstName, #lastName, #middleName, #email, #address').val('');
        $('#mobile').val('09');
        $('#firstName, #lastName, #middleName').prop('readonly', false);
        $('#enableMiddleName').prop('disabled', false).prop('checked', true);
        $('#middleName').prop('disabled', false);

        selectedClient = null;
    }
    // If no client was selected, do nothing — keep user-typed inputs intact
});


function customReason() {
    const $select = $('#resched-reason');
    // Handle custom input toggle
    $select.on('change', function () {
        if ($(this).val() === 'other') {
            $('#custom-reason-group').show();
        } else {
            $('#custom-reason-group').hide();
            $('#custom-reason').val('');
        }
    });
}


function formatScheduleTime(dateTimeStr) {
    const date = new Date(dateTimeStr);

    // Check if date is valid
    if (isNaN(date.getTime())) {
        console.warn('Invalid date passed to formatScheduleTime:', dateTimeStr);
        return ''; // Or return 'Invalid date'
    }

    const weekday = date.toLocaleString('en-PH', {
        weekday: 'long'
    });
    const month = date.toLocaleString('en-PH', {
        month: 'long'
    });
    const day = date.getDate();
    const year = date.getFullYear();

    let hour = date.getHours();
    const minute = date.getMinutes().toString().padStart(2, '0');
    const ampm = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12 || 12;

    return `${weekday}, ${month} ${day}, ${year} at ${hour}:${minute} ${ampm}`;
}


function formatDuration(timeStr) {
    if (!timeStr) return 'N/A';

    const parts = timeStr.split(':');
    const hours = parseInt(parts[0], 10);
    const minutes = parseInt(parts[1], 10);

    let result = [];
    if (hours > 0) result.push(hours + ' hour' + (hours > 1 ? 's' : ''));
    if (minutes > 0) result.push(minutes + ' minute' + (minutes > 1 ? 's' : ''));
    return result.join(' ');
}


function formatPeso(amount) {
    if (amount == null || isNaN(amount)) return '₱ 0.00';
    return Number(amount).toLocaleString('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2
    });
}


const requestSched_fp = flatpickr("#requestSched", {
    enableTime: true,
    time_24hr: false,
    dateFormat: "l, F j, Y at h:i K",
    minDate: "today",
    minTime: "08:30",
    maxTime: "16:00", // 8:30am to 4:00pm lang pwede mag request appointment
    allowInput: false,
    disable: [
        function (date) {
            // Disable Sundays and Saturdays : para sa dayoffs, kung meron
            return date.getDay() === 0 || date.getDay() === 6;
        }
    ]
});



let selectedVehicleId = null;
let selectedVehicle = null;
let selectedClient = null;
let selectedServices = [];
let selectedDateTime = null;
let currentOpenModal = null;
let tempNewVehicles = [];

let selectedRequestType = null;
let selectedRequestStatus = null;




// Function to lock to now
function setToNowAndLock() {
    const now = new Date();
    requestSched_fp.setDate(now, true);
    requestSched_fp.set('allowInput', false);
    $('#requestSched').prop('readonly', true);
}

// Function to enable input
function enableScheduleInput() {
    requestSched_fp.set('allowInput', true);
    requestSched_fp.clear();
    $('#requestSched').prop('readonly', false);
}

// On radio change
$('input[name="requestType"]').on('change', function () {
    const type = $(this).val();
    const now = new Date();
    const day = now.getDay(); // 0 = Sun, 6 = Sat
    const hours = now.getHours();
    const minutes = now.getMinutes();
    const totalMinutes = hours * 60 + minutes;

    selectedRequestType = type;
    selectedRequestStatus = (type === 'walk-in') ? 'Approved' : 'Pending';

    $('#finalRequestType').val(selectedRequestType);
    $('#finalRequestStatus').val(selectedRequestStatus);

    if (type === 'walk-in') {
        // Allowed time: Monday–Friday, 8:00 AM to 5:00 PM
        const openTime = 8 * 60;    // 8:00 AM
        const closeTime = 16 * 60 ; // 5:00 PM

        if (day === 0 || day === 6 || totalMinutes < openTime || totalMinutes > closeTime) {
            $('#addServiceRequest-5').modal('hide');
            Swal.fire({
                iconHtml: '<i class="bx bx-error-circle"></i>',
                title: 'Walk-in Not Allowed',
                text: 'Walk-in requests are only allowed on weekdays and between 8:00 AM and 5:00 PM.',
            }).then(() => {
                $('#addServiceRequest-5').modal('show');
            });

            // Revert back to appointment
            $('#appointmentRadio').prop('checked', true).trigger('change');
            return;
        }

        setToNowAndLock();
    } else {
        enableScheduleInput();
    }
});

// Set default type on page load
$(document).ready(function () {
    const defaultType = $('input[name="requestType"]:checked').val();
    selectedRequestType = defaultType;
    selectedRequestStatus = defaultType === 'walk-in' ? 'Approved' : 'Pending';
    $('#finalRequestType').val(selectedRequestType);
    $('#finalRequestStatus').val(selectedRequestStatus);

    // Set input mode on load
    if (defaultType === 'walk-in') {
        setToNowAndLock();
    } else {
        enableScheduleInput();
    }
});

$('.modal').on('show.bs.modal', function () { //SAVE CURRENTLY OPEN MODAL
    currentOpenModal = `#${$(this).attr('id')}`;
});


$(document).on('click', '#closeAddRequestModal, .closeAddRequestModal', function () {
    const firstName = $('#firstName').val().trim();
    const lastName = $('#lastName').val().trim();
    const middleName = $('#middleName').val().trim();
    const email = $('#email').val().trim();
    const address = $('#address').val().trim();
    const mobile = $('#mobile').val().trim();

    const hasInput =
        selectedClient != null ||
        firstName !== '' ||
        lastName !== '' ||
        middleName !== '' ||
        email !== '' ||
        address !== '' ||
        (mobile !== '' && mobile !== '09');

    if (hasInput) {
        $('#addServiceRequest-1').modal('hide');
        Swal.fire({
            iconHtml: '<i class="bx bx-info-circle"></i>',
            title: 'Discard Changes?',
            text: 'Are you sure you want to cancel this request? All entered details will be discarded.',
            showCancelButton: true,
            confirmButtonText: 'Discard',
            cancelButtonText: 'Keep Editing'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#clientSelectNote').hide();
                clearRequestInputs();
            } else {
                if (currentOpenModal) {
                    $(currentOpenModal).modal('show');
                }
            }
        });
    } else {
        // No input to discard, just close the modal
        if (currentOpenModal) {
            $(currentOpenModal).modal('hide');
        }
        $('#clientSelectNote').hide();
    }
});


function clearRequestInputs() {
    selectedVehicleId = null;
    selectedVehicle = null;
    selectedClient = null;
    selectedServices = [];
    selectedDateTime = null;
    requestSched_fp.clear();
    tempNewVehicles = []; // Keep this globally
    selectedRequestType = null;
    selectedRequestStatus = null;

    $('#clientSelectNote').hide();
    // Clear form inputs
    $('#firstName, #lastName, #middleName, #email, #address').val('');
    $('#mobile').val('09');
    $('#middleName').prop('disabled', true);
    $('#enableMiddleName').prop('checked', false);

    // Optional: Clear dropdown
    $('#selectClient').val('').trigger('change');

    $('#requestSched').val('');
    $('#requestTime').val('');

    $('#make-inpt, #model-inpt, #color-inpt, #plate-inpt, #vtr-other, #vft-other').val('');
    $('#vtr-inpt').val('');
    $('#vft-inpt').val('');
    $('#vtr-other, #vft-other').addClass('d-none');

    // Optional: Clear vehicle and service UI
    // renderVehicles([]);
    // displayAllServices([]);
}



$(document).on('click', '#addbtn', function () { // SHOW CLIENTS MODAL
    clearRequestInputs();
    console.log('FINAL RESULT:', selectedClient, selectedServices, selectedVehicle, selectedDateTime);
    document.getElementById('selectClient').disabled = true;
    document.getElementById('enableSelectClient').checked = false;
    $.ajax({
        url: 'handlers/serviceRequests-handler.php',
        dataType: 'json',
        method: 'POST',
        data: {
            row_id: ActiveRowId,
            action: 'getClientsAndServices'
        },
        success: function (response) {

            allServices = response.services;
            allClients = response.clients;

            select2ForSelectClient();

        },
        error: function (err) {
            Swal.fire({
                iconHtml: '<i class=\'bx bx-x-circle\'></i>',
                title: 'Error',
                text: 'Something went wrong'
            });
            console.error('AJAX error:', err);
        }
    });




    $('#addServiceRequest-1').modal('show');
});


$(document).on('click', '#showVehiclesModal', function () { // CLIENTS MODAL / SHOW VEHICLES MODAL
    
    const form = document.getElementById('clientFormModal');

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }



    const mobile = document.getElementById('mobile').value.trim();
    const email = document.getElementById('email').value.trim();
    const mobilePattern = /^09\d{9}$/;

    // Check if both are empty or default
    if ((mobile === '' || mobile === '09') && email === '') {
        $('#addServiceRequest-1').modal('hide');
        Swal.fire({
            ...swalOptions,
            iconHtml: '<i class="bx bx-error-circle"></i>',
            title: 'Missing Contact Info',
            text: 'Please provide at least a valid mobile number or an email address.'
        }).then(() => {
            $('#addServiceRequest-1').modal('show');
        });
        return;
    }

    // If mobile is not empty or '09', validate it
    if (mobile !== '' && mobile !== '09' && !mobilePattern.test(mobile)) {
        $('#addServiceRequest-1').modal('hide');
        Swal.fire({
            ...swalOptions,
            iconHtml: '<i class="bx bx-error-circle"></i>',
            title: 'Invalid Mobile Number',
            text: 'Mobile number must start with 09 and be 11 digits long (e.g., 09123456789).'
        }).then(() => {
            $('#addServiceRequest-1').modal('show');
        });
        return;
    }



    $('#addServiceRequest-1').modal('hide');

    const selectedId = $('#selectClient').val();

    if (selectedId) {
        // Compare original details with inputs
        const inputMobile = $('#mobile').val();
        const inputEmail = $('#email').val();
        const inputAddress = $('#address').val();

        const original = selectedClient;

        const mobileChanged = inputMobile !== original.contact_number;
        const emailChanged = inputEmail !== original.email;
        const addressChanged = inputAddress !== original.address;

        // Save change status
        selectedClient.detailsChanged = mobileChanged || emailChanged || addressChanged;

        // ✅ Update values so PHP receives the latest ones
        selectedClient.contact_number = inputMobile;
        selectedClient.email = inputEmail;
        selectedClient.address = inputAddress;
        
        $.ajax({
            url: 'handlers/serviceRequests-handler.php',
            method: 'POST',
            data: {
                clientId: selectedId,
                action: 'getClientsVehicles'
            },
            success: function (response) {

                // Merge unsaved temp vehicles for this client
                const newVehiclesForClient = tempNewVehicles.filter(v => v.client_id == selectedClient.client_id);

                clientsAllVehicles = [...response.vehicles, ...newVehiclesForClient];
                
                // ✅ Remove (filter out) unavailable vehicles
                // clientsAllVehicles = clientsAllVehicles.filter(v => !v.is_unavailable);

                renderVehicles(clientsAllVehicles);

                $('#addServiceRequest-2').modal('show');
            },
            error: function (err) {
                Swal.fire({
                    iconHtml: '<i class="bx bx-x-circle"></i>',
                    title: 'Error',
                    text: 'Something went wrong'
                });
                console.error('AJAX error:', err);
            }
        });

        return;
    }

    // If no client selected from dropdown

    const inputEmail = $('#email').val().trim().toLowerCase();
    const inputContact = $('#mobile').val().trim().toLowerCase();

    //RESETS THE VEHICLES DISPLAY IF THERES A PREVIOSLY SELECTED CLIENT

    selectedVehicle = null;
    selectedVehicleId = null;
    clientsAllVehicles = [];
    renderVehicles([]); // VISUALLY clear vehicle list

    const match = allClients.find(c =>
        c.contact_number.toLowerCase() === inputContact ||
        c.email.toLowerCase() === inputEmail
    );

    if (match) {
        // Prompt confirmation
        Swal.fire({
            iconHtml: '<i class="bx bx-user-circle"></i>',
            title: 'Previous Client Found',
            html: `A client with matching contact information already exists in the system. <br><br>
                Name: <b>${match.first_name} ${match.last_name}</b><br>
                Email: <b>${match.email}</b><br>
                Number: <b>${match.contact_number}</b>`,
            showCancelButton: true,
            confirmButtonText: 'Use This Profile',
            cancelButtonText: 'Cancel',
        }).then((result) => {
            if (result.isConfirmed) {

                // if (match.is_unavailable) {
                //     Swal.fire({
                //         iconHtml: '<i class="bx bx-error-circle"></i>',
                //         title: 'Client Unavailable',
                //         text: 'This client currently has an ongoing service request and cannot be selected.',
                //         confirmButtonText: 'Back',
                //         allowOutsideClick: false
                //     }).then(() => {
                //         $('#addServiceRequest-1').modal('show');
                //     });
                // } else {
                    selectedClient = match;
                    selectedClient.detailsChanged = false;

                    // Prefill
                    $('#firstName').val(match.first_name).prop('readonly', true);
                    $('#lastName').val(match.last_name).prop('readonly', true);

                    if (match.middle_name) {
                        $('#enableMiddleName').prop('checked', true);
                        $('#middleName').val(match.middle_name).prop('readonly', true).prop('disabled', false);
                    } else {
                        $('#enableMiddleName').prop('checked', false);
                        $('#middleName').val('').prop('disabled', true);
                    }

                    $('#mobile').val(match.contact_number || '');
                    $('#email').val(match.email || '');
                    $('#address').val(match.address || '');

                    // Load vehicles
                    $.ajax({
                        url: 'handlers/serviceRequests-handler.php',
                        method: 'POST',
                        data: {
                            clientId: match.client_id,
                            action: 'getClientsVehicles'
                        },
                        success: function (response) {
                            clientsAllVehicles = response.vehicles;
                            renderVehicles(clientsAllVehicles);
                            $('#addServiceRequest-2').modal('show');
                        },
                        error: function (err) {
                            Swal.fire({
                                iconHtml: '<i class="bx bx-x-circle"></i>',
                                title: 'Error',
                                text: 'Something went wrong'
                            });
                            console.error('AJAX error:', err);
                        }
                    });
                // }
            } else {
                // Show form again so user can fix or cancel
                $('#addServiceRequest-1').modal('show');
            }
        });
    } else {
        // Only runs if no match
        selectedClient = {
            client_id: null,
            first_name: $('#firstName').val(),
            last_name: $('#lastName').val(),
            middle_name: $('#middleName').val(),
            contact_number: $('#mobile').val(),
            email: $('#email').val(),
            address: $('#address').val(),
            isNew: true,
            detailsChanged: false
        };

        displayAllServices();
        $('#addServiceRequest-2').modal('show');
    }
});


$(document).on('click', '#showServicesModal', function () { // VEHICLES MODAL / SHOW SERVICES MODAL
    $('#addServiceRequest-2').modal('hide');


    if (!selectedVehicle) {
        Swal.fire({
            ...swalOptions,
            iconHtml: '<i class="bx bx-error-circle"></i>',
            title: 'No Vehicle Selected',
            text: 'Please select a vehicle to proceed to the next process.'
        }).then(() => {
            $('#addServiceRequest-2').modal('show');
        });
    } else {
        displayAllServices();
        $('#addServiceRequest-3').modal('show');
    }
});


$(document).on('click', '#showCommentsModal', function () { // SERVICES MODAL / SHOW COMMENTS MODAL
    console.log('Selected services before proceeding:', selectedServices);

    if (!selectedServices || selectedServices.length === 0) {
        $('#addServiceRequest-3').modal('hide');
        Swal.fire({
            ...swalOptions,
            iconHtml: '<i class=\'bx bx-error-circle\'></i>',
            title: 'No Service Selected',
            text: 'Select atleast one service to proceed to the next process.'
        }).then(() => {
            $('#addServiceRequest-3').modal('show');
        });
    } else {



        renderServiceInputs();
        $('#addServiceRequest-3').modal('hide');

        $('#addServiceRequest-4').modal('show');
    }

});


$(document).on('click', '#showSchedModal', function () { // COMMMENTS MODAL / SHOW SCHED MODAL
    $('#addServiceRequest-4').modal('hide');


    $('#addServiceRequest-5').modal('show');

});


$(document).on('click', '#showSummaryModal', function () { // SCHED MODAL / SHOW SUMMARY MODAL
    // Make sure a date is selected first
    const requestType = $('#finalRequestType').val();
    const requestStatus = $('#finalRequestStatus').val();

    // Check if request type or status is missing
    if (!requestType || !requestStatus) {
        $('#addServiceRequest-5').modal('hide');
        Swal.fire({
            ...swalOptions,
            iconHtml: '<i class="bx bx-error-circle"></i>',
            title: 'Missing Request Type',
            text: 'Please select a request type before proceeding.'
        }).then(() => {
            $('#addServiceRequest-5').modal('show');
        });
        return;
    }
    if (!requestSched_fp.selectedDates || requestSched_fp.selectedDates.length === 0 && selectedDateTime == null) {
        $('#addServiceRequest-5').modal('hide');
        Swal.fire({
            ...swalOptions,
            iconHtml: '<i class="bx bx-error-circle"></i>',
            title: 'Missing Schedule',
            text: 'Please select a date and time before proceeding.'
        }).then(() => {
            $('#addServiceRequest-5').modal('show');
        });
        return;
    }

    const selected = requestSched_fp.selectedDates[0];
    const datetime = requestSched_fp.formatDate(selected, "Y-m-d H:i:S");

    if (!selectedServices || selectedServices.length === 0) {
        $('#addServiceRequest-5').modal('hide');
        Swal.fire({
            ...swalOptions,
            iconHtml: '<i class="bx bx-error-circle"></i>',
            title: 'No Service Selected',
            text: 'Select at least one service to proceed to the next process.'
        }).then(() => {
            $('#addServiceRequest-5').modal('show');
        });
        return;
    }

    $.ajax({
        url: 'handlers/serviceRequests-handler.php',
        method: 'POST',
        data: {
            schedDate: datetime,
            services: selectedServices,
            action: 'validateSchedule'
        },
        success: function (response) {
            console.log(response);
            if (response.status === true) {
                $('#addServiceRequest-5').modal('hide');

                selectedDateTime = datetime;

                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-info-circle"></i>',
                    title: 'Slot Available',
                    html: `Currently ${response.overlap} out of 4 booking/s are scheduled during this time.`,
                    showConfirmButton: false
                }).then(() => {
                    updateSummaryDetails();
                    $('#addServiceRequest-6').modal('show');
                });
            } else {
                $('#addServiceRequest-5').modal('hide');
                Swal.fire({
                    iconHtml: '<i class="bx bx-error-circle"></i>',
                    title: 'Schedule Conflict',
                    html: response.suggested ?
                        `
                                The selected schedule has reached the maximum number of bookings.<br><br>
                                <strong>Suggested next available time:</strong><br>
                                ${formatScheduleTime(response.suggested)}
                            ` : 'The selected schedule has reached the maximum number of bookings.',
                    showCancelButton: response.suggested ? true : false,
                    confirmButtonText: response.suggested ? 'Use Suggested Time' : 'OK',
                    cancelButtonText: 'Cancel'
                }).then(result => {
                    if (result.isConfirmed && response.suggested) {
                        const picker = $('#requestSched')[0]._flatpickr;
                        picker.setDate(new Date(response.suggested), true);


                        selectedDateTime = response.suggested;

                        Swal.fire({
                            ...swalOptions,
                            iconHtml: '<i class="bx bx-check-circle"></i>',
                            title: 'Suggested Time Applied',
                            text: 'The suggested schedule has been set.',
                        }).then(() => {
                            updateSummaryDetails();
                            $('#addServiceRequest-6').modal('show');
                        });
                    } else {
                        $('#addServiceRequest-5').modal('show');
                    }
                });
            }
        },
        error: function (err) {
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class="bx bx-x-circle"></i>',
                title: 'Error',
                text: 'Something went wrong while validating the schedule.'
            });
            console.error('AJAX error:', err);
        }
    });
});


$(document).on('click', '#addVehicleBtn', function () {
    $('#addServiceRequest-2').modal('hide');
    $('#addVehicleModal').modal('show');
});


$(document).on('click', '#addVehicleM', function () {
    const make = $('#make-inpt').val().trim();
    const model = $('#model-inpt').val().trim();
    const color = $('#color-inpt').val().trim();
    const plate = $('#plate-inpt').val().trim();

    let transmission = $('#vtr-inpt').val();
    if (transmission === 'Other') {
        transmission = $('#vtr-other').val().trim();
    }

    let fuel = $('#vft-inpt').val();
    if (fuel === 'Other') {
        fuel = $('#vft-other').val().trim();
    }


    if (!make || !model || !transmission || !fuel) {
        $('#addVehicleModal').modal('hide');
        Swal.fire({
            ...swalOptions,
            iconHtml: '<i class="bx bx-error-circle"></i>',
            title: 'Missing Fields',
            text: 'Please fill out all required fields before continuing.'
        }).then(() => {
            $('#addVehicleModal').modal('show');
        });
        return;
    }

    // Generate a temporary unique ID (negative to avoid DB conflict)
    const tempId = -(Date.now());

    const newVehicle = {
        vehicle_id: tempId,
        client_id: selectedClient?.client_id || null,
        make,
        model,
        color,
        plate_number: plate || null,
        transmission_type: transmission,
        fuel_type: fuel,
        isNew: true
    };


    tempNewVehicles.push(newVehicle); // Save to temp list
    clientsAllVehicles.push(newVehicle);

    selectedVehicleId = tempId;
    selectedVehicle = newVehicle;

    renderVehicles(clientsAllVehicles);

    // Highlight the newly added and selected vehicle card
    setTimeout(() => {
        $(`.vehicle-card[data-id="${tempId}"]`).addClass('selected-vehicle');
    }, 0);

    $('#addVehicleModal').modal('hide');
    $('#addServiceRequest-2').modal('show');

    $('#make-inpt, #model-inpt, #color-inpt, #plate-inpt, #vtr-other, #vft-other').val('');
    $('#vtr-inpt').val('');
    $('#vft-inpt').val('');
    $('#vtr-other, #vft-other').addClass('d-none');
});


$(document).on('click', '#addRequest', function () { // SUMMARY MODAL / SAVE REQUEST
    // $('#addServiceRequest-6').modal('hide');
    const requestData = {
        action: 'submit_request',
        client_data: selectedClient,
        vehicle_data: selectedVehicle,
        selected_services: selectedServices,
        schedule: selectedDateTime, // e.g. '2025-06-27 12:00:00'
        request_type: $('#finalRequestType').val(),
        request_status: $('#finalRequestStatus').val()
    }; //DITO
    console.log(requestData);

    $.ajax({
        url: 'handlers/serviceRequests-handler.php',
        method: 'POST',
        data: requestData,
        dataType: 'json',
        beforeSend: () => {
            $('.addServiceRequest-6').modal('hide');
            Swal.fire({
                title: 'Submitting...',
                text: 'Please wait while we submit your request.',
                didOpen: () => Swal.showLoading(),
                allowOutsideClick: false,
                allowEscapeKey: false
            });
        },
        success: (response) => {
            console.log(response);
            if (response.status === 'success') {
                $('.addServiceRequest-6').modal('hide');
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-check-circle"></i>',
                    title: 'Request Submitted',
                    text: response.message
                }).then(() => {
                    clearRequestInputs();
                    location.reload();
                });
            } else {
                Swal.fire({
                    ...swalOptions,
                    iconHtml: '<i class="bx bx-x-circle"></i>',
                    title: 'Submission Failed',
                    text: response.message || 'An unexpected error occurred.'
                }).then(() => {
                    $('.addServiceRequest-6').modal('show');
                });
            }
        },
        error: (xhr, status, error) => {
            console.error('AJAX Error:', error);
            Swal.fire({
                ...swalOptions,
                iconHtml: '<i class="bx bx-x-circle"></i>',
                title: 'Server Error',
                text: 'Something went wrong while submitting the request.'
            });
        }
    });
});



function updateSummaryDetails() {
    // 1. Client Info
    $('#sum-client').html(`
        <b>Client Information</b>
        <p class="ellipsis-tooltip">${selectedClient.last_name}, ${selectedClient.first_name} ${selectedClient.middle_name || ''}</p>
        <p class="ellipsis-tooltip">${selectedClient.contact_number || '-'}</p>
        <p class="ellipsis-tooltip">${selectedClient.email || '-'}</p>
        <p class="address-only">${selectedClient.address || '-'}</p>
    `);

    // 2. Vehicle Info
    $('#sum-vehicle').html(`
        <b>Vehicle Information</b>
        <p class="ellipsis-tooltip">${selectedVehicle.make} ${selectedVehicle.model}</p>
        <p class="ellipsis-tooltip">${selectedVehicle.plate_number || '—'}</p>
        <p class="ellipsis-tooltip">${selectedVehicle.color || '-'}</p>
        <p class="ellipsis-tooltip">${selectedVehicle.transmission_type}</p>
        <p class="ellipsis-tooltip">${selectedVehicle.fuel_type}</p>
    `);

    // 3. Chosen Services
    let serviceBtns = selectedServices.map((s, index) => `
        <button 
            class="service-comment-btn ${index === 0 ? 'active' : ''}" 
            data-comment="${s.comment || ''}" 
            data-service="${s.name}" 
            data-index="${index}">
            ${s.name}
        </button>
    `).join('');

    $('#sum-service').html(`
        <b>Chosen Services (${selectedServices.length})</b><br>
        ${serviceBtns}
    `);

    // 4. Comment per selected service
    const firstService = selectedServices[0];
    $('#sum-comment').html(`
        <b>Descriptions/Comments</b>
        <p class="comment-display">${firstService.comment || 'No comment provided.'}</p>
    `);

    // 5. Schedule
    if (selectedDateTime && selectedDateTime.trim() !== '') {
        const formatted = formatScheduleTime(selectedDateTime); // already your helper function
        const [dateStr, timeStr] = formatted.split(' at ');

        $('#sum-sched').html(`
            <b>Service Schedule</b>
            <p>${dateStr}</p>
            <p>${timeStr}</p>
        `);
    } else {
        $('#sum-sched').html(`
            <b>Service Schedule</b>
            <p>—</p>
            <p>—</p>
        `);
    }
}


$(document).on('click', '.service-comment-btn', function () {
    $('.service-comment-btn').removeClass('active'); // remove from all
    $(this).addClass('active'); // add to clicked

    const comment = ($(this).data('comment') + '').trim() || 'No comment provided.';
    const serviceName = $(this).data('service');

    $('#sum-comment .comment-display').html(`${comment}`);
});


function renderVehicles(vehicles) {
    const container = document.getElementById('vehicleList');

    // Clear all except the Add Vehicle card
    container.querySelectorAll('.vehicle-card:not(.add-vehicle-card)').forEach(el => el.remove());

    // Remove "no vehicle" message if exists
    const noVehicleText = container.querySelector('.no-vehicle-text');
    if (noVehicleText) noVehicleText.remove();

    if (!vehicles || vehicles.length === 0) {
        const noDataDiv = document.createElement('div');
        noDataDiv.className = 'text-muted no-vehicle-text';
        noDataDiv.textContent = 'No registered vehicles for this client.';
        container.appendChild(noDataDiv);
        return;
    }

    // Create and insert vehicle cards
    vehicles.forEach(vehicle => {
        const isSelected = selectedVehicle && selectedVehicle.vehicle_id == vehicle.vehicle_id;
        const isUnavailable = vehicle.is_unavailable === true || vehicle.is_unavailable === 1 || vehicle.is_unavailable === "true";

        const make = vehicle.make || 'N/A';
        const model = vehicle.model || 'N/A';
        const plate = vehicle.plate_number || 'N/A';
        const color = vehicle.color || '—';
        const transmission = vehicle.transmission_type || '—';
        const fuel = vehicle.fuel_type || '—';

        const card = document.createElement('div');
        card.classList.add('vehicle-card');
        if (isSelected) card.classList.add('selected-vehicle');
        if (isUnavailable) card.classList.add('vehicle-unavailable');
        card.setAttribute('data-id', vehicle.vehicle_id);
        card.style.cursor = isUnavailable ? 'not-allowed' : 'pointer';
        card.style.opacity = isUnavailable ? '0.6' : '1.0';

        card.innerHTML = `
            <div class="vehicle-card-body">
                <h6 class="vehicle-card-title">${make} ${model}</h6>
                <strong class="vehicle-card-text">${plate}</strong>
                <small class="vehicle-card-text">${color} &#8226; ${transmission} &#8226; ${fuel}</small>
                ${isUnavailable ? `<div class="text-danger mt-1"><small><i>This vehicle is currently part of a pending or ongoing service request.</i></small></div>` : ''}
            </div>
        `;

        if (!isUnavailable) {
        // Add click handler
        card.addEventListener('click', function () {
            selectedVehicleId = vehicle.vehicle_id;
            selectedVehicle = vehicle;
            saveSelectedVehicle(vehicle.vehicle_id);

            // Remove any previously selected highlight
            document.querySelectorAll('.vehicle-card').forEach(c => c.classList.remove('selected-vehicle'));

            this.classList.add('selected-vehicle');
        });
    }
        container.appendChild(card);
    });
}


function saveSelectedVehicle(vehicleId) { // SAVE SELECTED VEHICLE TO THE 'selectedVehicle' VARIABLE
    // Find vehicle data from list
    const vehicle = clientsAllVehicles.find(v => v.vehicle_id == vehicleId);

    if (vehicle) {
        selectedVehicle = {
            vehicle_id: vehicle.vehicle_id,
            plate_number: vehicle.plate_number,
            make: vehicle.make,
            model: vehicle.model,
            color: vehicle.color || null, // optional field
            transmission_type: vehicle.transmission_type,
            fuel_type: vehicle.fuel_type
        };

    } else {
        console.warn('Vehicle not found in list');
    }
}


function select2ForSelectClient() { // INITIALIZE SELECT2 FOR / SAVE SELECTED CLIENT / PREFILL INPUTS FROM THE SELECTED CLIENT
    const clientSelect = $('#selectClient');

    // Destroy if already initialized
    if (clientSelect.data('select2')) {
        clientSelect.select2('destroy');
    }

    // Clear old options except the first
    clientSelect.find('option:not(:first)').remove();
    console.log(allClients);
    allClients.forEach((client, index) => {
        // if (client.is_unavailable) return; // Skip if unavailable
        const fullName = `${client.first_name} ${client.last_name}`;
        const option = new Option(`${fullName} (${client.email || "no email found"})`, client.client_id, false, false);

        clientSelect.append(option);
    });
    // Initialize Select2
    clientSelect.select2({
        dropdownParent: $('#addServiceRequest-1'),
        placeholder: 'Select a client',
        width: '100%'
    });

    // Add custom placeholder inside search
    clientSelect.on('select2:open', function () {
        setTimeout(() => {
            const searchField = document.querySelector('.select2-container--open .select2-search__field');
            if (searchField) {
                searchField.placeholder = 'Look for Clients...';
            }
        }, 0);
    });

    clientSelect.on('change', function () {
        selectedVehicle = null;
        selectedVehicleId = null;
        clientsAllVehicles = [];
        renderVehicles([]); //VISUALLY clear vehicle list
        const selectedId = $(this).val();


        if (!selectedId) {
            selectedClient = null;
            // If cleared via the clear (×) button, reset all inputs
            $('#firstName, #lastName, #middleName, #email, #address').val('');
            $('#mobile').val('09');
            // Re-enable and make editable
            $('#firstName').prop('readonly', false);
            $('#lastName').prop('readonly', false);
            $('#middleName').prop('readonly', false);
            $('#enableMiddleName').prop('disabled', false).prop('checked', true);
            $('#middleName').prop('disabled', false);
            return;
        }

        selectedClient = allClients.find(c => c.client_id == selectedId); //  Save globally

        if (selectedClient) {
            $('#firstName').prop('readonly', true);
            $('#lastName').prop('readonly', true);
            $('#enableMiddleName').prop('disabled', true);
            $('#middleName').prop('readonly', true); // Optional

            $('#firstName').val(selectedClient.first_name);
            $('#lastName').val(selectedClient.last_name);

            // Middle Name Logic
            if (selectedClient.middle_name) {
                $('#enableMiddleName').prop('checked', true);
                $('#middleName').prop('disabled', false).val(selectedClient.middle_name);
            } else {
                $('#enableMiddleName').prop('checked', false);
                $('#middleName').prop('disabled', true).val('');
            }

            // Mobile
            if (selectedClient.contact_number) {
                $('#mobile').val(selectedClient.contact_number);
            } else {
                $('#mobile').val('09');
            }

            // Email & Address
            $('#email').val(selectedClient.email || '');
            $('#address').val(selectedClient.address || '');
        }
    });
}


function displayAllServices() { // DISPLAYS ALL SERVICES OFFERED IN CHECKBOXES, AND SAVES THE SELECTED ONES
    const serviceList = document.getElementById('serviceList');
    const serviceSearch = document.getElementById('serviceSearch');


    function renderServices(filter = '') {
        let content = '';

        allServices.forEach(service => {
            const {
                service_id,
                service_name,
                description,
                status,
                estimated_duration,
                labor_cost
            } = service;

            const nameMatch = service_name.toLowerCase().includes(filter.toLowerCase());
            const descMatch = description.toLowerCase().includes(filter.toLowerCase());

            if (nameMatch || descMatch) {
                const formattedDuration = formatDuration(estimated_duration);
                const isActive = status.toLowerCase() === 'active';
                const disabledAttr = isActive ? '' : 'disabled';
                const labelStyle = isActive ? '' : 'style="color: gray;"';
                const statusTag = isActive ? '' : `- <small id="inactive-text"> Inactive</small>`;
                const isChecked = selectedServices.some(s => s.id === String(service_id)) ? 'checked' : '';

                content += `
            <div class="form-check" id="service-list">
                <input class="form-check-input service-checkbox" type="checkbox" value="${service_id}" id="service${service_id}" ${disabledAttr} ${isChecked}>
                <label class="form-check-label" for="service${service_id}" ${labelStyle}>
                    <strong>${service_name}</strong> ${statusTag}<br>
                    <small>${description}</small><br>
                    <small>${formattedDuration} - &#x20B1;${labor_cost}</small>
                </label>
            </div>
        `;
            }
        });

        serviceList.innerHTML = content || `<p id="noServ">No services match.</p>`;

        // Attach listeners to checkboxes AFTER rendering
        document.querySelectorAll('.service-checkbox:not([disabled])').forEach(cb => {
            cb.addEventListener('change', () => {
                const serviceId = cb.value;
                const service = allServices.find(s => s.service_id === serviceId);

                if (cb.checked) {
                    if (!selectedServices.some(s => s.id === serviceId)) {
                        selectedServices.push({
                            id: serviceId,
                            name: service.service_name,
                            estimated_duration: service.estimated_duration,
                            labor_cost: service.labor_cost
                        });
                    }
                } else {
                    const index = selectedServices.findIndex(s => s.id === serviceId);
                    if (index !== -1) {
                        selectedServices.splice(index, 1);
                    }
                }

                updateSummary();
            });
        });

        updateSummary();
    }

    function updateSummary() {
        let totalSeconds = 0;
        const selectedNames = [];

        selectedServices.forEach(service => {
            selectedNames.push(service.name);

            const fullService = allServices.find(s => s.service_id === service.id);
            if (fullService) {
                const [h, m, s] = fullService.estimated_duration.split(':').map(Number);
                totalSeconds += h * 3600 + m * 60 + s;
            }
        });

        const hours = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
        const minutes = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
        const seconds = String(totalSeconds % 60).padStart(2, '0');
        const totalTime = `${hours}:${minutes}:${seconds}`;

        document.getElementById('selectedNames').textContent = selectedNames.join(', ') || 'none';
        document.getElementById('totalDuration').textContent = formatDuration(totalTime) || '0';
    }

    serviceSearch.addEventListener('input', e => {
        renderServices(e.target.value);
    });

    renderServices();
}


function renderServiceInputs() { // DISPLAY COMMENT INPUTS OF THE SELECTED SERVICES
    const container = document.getElementById('service-comments-container');
    container.innerHTML = ''; // Clear first

    selectedServices.forEach(service => {
        const div = document.createElement('div');
        div.classList.add('service-input');
        const commentText = service.comment ? service.comment : '';
        div.innerHTML = `
    <label class="form-control" for="comment-${service.id}"><strong>${service.name}</strong></label>
    <textarea class="form-control" id="comment-${service.id}" placeholder="Enter comment for ${service.name}">${commentText}</textarea>
    <br>
    
`;

        container.appendChild(div);
    });
}


function saveServiceDescriptions() { //SAVES THE COMMENTS IN THE selectedServices object
    selectedServices.forEach(service => {
        const comment = document.getElementById(`comment-${service.id}`).value.trim();
        service.comment = comment; // Add new `comment` field to each service
    });

}