$(document).ready(function() {
    // Initialize DataTables
    if($.fn.DataTable) {
        $('.data-table').DataTable({
            responsive: true,
            pageLength: 25,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search..."
            }
        });
    }
    
    // Form validation
    if($.fn.validate) {
        $('form').validate({
            errorElement: 'div',
            errorClass: 'invalid-feedback',
            highlight: function(element) {
                $(element).addClass('is-invalid');
            },
            unhighlight: function(element) {
                $(element).removeClass('is-invalid');
            },
            errorPlacement: function(error, element) {
                if(element.parent('.input-group').length) {
                    error.insertAfter(element.parent());
                } else {
                    error.insertAfter(element);
                }
            }
        });
    }
    
    // Tooltips and Popovers
    $('[data-toggle="tooltip"]').tooltip();
    $('[data-toggle="popover"]').popover();
    
    // Confirm delete actions
    $('.confirm-delete').click(function(e) {
        if(!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            e.preventDefault();
        }
    });
    
    // Date picker initialization
    if($.fn.datepicker) {
        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true
        });
    }
    
    // Asset search functionality
    $('#assetSearchInput').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $("#assetTable tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
});

// Function to generate a QR code URL for an asset
function getAssetQrUrl(assetId, assetTag) {
    const baseUrl = window.location.origin + '/asset_management_system/';
    return baseUrl + 'modules/inventory/view.php?id=' + assetId + '&tag=' + assetTag;
}

// Function to handle asset assignment form
function setupAssetAssignmentForm() {
    const assignmentForm = $('#assignmentForm');
    if(!assignmentForm.length) return;
    
    // Handle return date requirements based on assignment type
    $('#assignmentType').change(function() {
        const val = $(this).val();
        if(val === 'temporary') {
            $('#returnDateGroup').show();
            $('#expectedReturnDate').prop('required', true);
        } else {
            $('#returnDateGroup').hide();
            $('#expectedReturnDate').prop('required', false);
        }
    });
    
    // Trigger change on page load
    $('#assignmentType').trigger('change');
}