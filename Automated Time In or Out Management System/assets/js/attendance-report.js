document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable with enhanced options
    var table = $('#attendance-table').DataTable({
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>" +
             "<'row'<'col-sm-12'B>>",
        buttons: [
            {
                extend: 'csv',
                text: '<i class="fas fa-file-csv me-2"></i>CSV',
                className: 'dt-button btn-csv',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel me-2"></i>Excel',
                className: 'dt-button btn-excel',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf me-2"></i>PDF',
                className: 'dt-button btn-pdf',
                exportOptions: {
                    columns: ':visible'
                },
                customize: function(doc) {
                    doc.content[1].table.widths = 
                        Array(doc.content[1].table.body[0].length + 1).join('*').split('');
                }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print me-2"></i>Print',
                className: 'dt-button btn-print',
                exportOptions: {
                    columns: ':visible'
                }
            }
        ],
        responsive: true,
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, 100], [5, 10, 25, 50, 100]],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search records...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No records available",
            infoFiltered: "(filtered from _MAX_ total entries)",
            paginate: {
                first: "<i class='fas fa-angle-double-left'></i>",
                last: "<i class='fas fa-angle-double-right'></i>",
                next: "<i class='fas fa-angle-right'></i>",
                previous: "<i class='fas fa-angle-left'></i>"
            }
        },
        initComplete: function() {
            $('.dataTables_filter label').addClass('form-label mb-0');
            $('.dataTables_length label').addClass('form-label mb-0');
        }
    });

    // Apply filters with enhanced UX
    $('#apply-filters').click(function() {
        var statusFilter = $('#status-filter').val();
        var startDate = $('#start-date').val();
        var endDate = $('#end-date').val();
        
        $.fn.dataTable.ext.search.push(
            function(settings, data, dataIndex) {
                var rowStatus = data[5] || '';
                var rowDate = new Date(data[4]);
                
                // Status filter
                if (statusFilter && !rowStatus.includes(statusFilter)) return false;
                
                // Date range filter
                if (startDate && new Date(startDate) > rowDate) return false;
                if (endDate && new Date(endDate) < rowDate) return false;
                
                return true;
            }
        );
        
        table.draw();
        $.fn.dataTable.ext.search.pop();
        
        // Show floating notification
        let filterCount = table.rows({ filter: 'applied' }).count();
        let notification = `<div class="alert alert-info alert-dismissible fade show alert-notification" role="alert">
            <i class="fas fa-info-circle me-2"></i> Showing ${filterCount} filtered records
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
        
        // Remove any existing notifications
        $('.alert-notification').remove();
        $('body').append(notification);
        
        // Auto-dismiss notification
        setTimeout(() => {
            $('.alert-notification').alert('close');
        }, 3000);
    });
    
    // Set today's date as default end date
    let today = new Date().toISOString().split('T')[0];
    $('#end-date').val(today);
});