<div class="container-fluid px-4">
    <h1 class="mt-4">Tables</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?= url('/') ?>">Dashboard</a></li>
        <li class="breadcrumb-item active">Tables</li>
    </ol>
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Service Requests
        </div>
        <div class="card-body">
            <table id="datatablesSimple">
                <thead>
                    <tr>
                        <th>Request</th>
                        <th>Type</th>
                        <th>City</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>JT-1021</td>
                        <td>Full Property Cleanout</td>
                        <td>Dallas</td>
                        <td>Scheduled</td>
                        <td>2026-02-01</td>
                    </tr>
                    <tr>
                        <td>JT-1022</td>
                        <td>Construction Debris</td>
                        <td>Phoenix</td>
                        <td>In Progress</td>
                        <td>2026-02-04</td>
                    </tr>
                    <tr>
                        <td>JT-1023</td>
                        <td>Appliance Removal</td>
                        <td>Miami</td>
                        <td>Completed</td>
                        <td>2026-02-06</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
