<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access - Centralized Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .error-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 60px 40px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="error-card">
                    <div class="text-danger mb-4">
                        <i class="fas fa-ban fa-5x"></i>
                    </div>
                    <h2 class="text-danger mb-3">Access Denied</h2>
                    <p class="lead mb-4">You don't have permission to access this resource.</p>
                    <p class="text-muted mb-4">Please contact your administrator if you believe this is an error.</p>
                    <a href="javascript:history.back()" class="btn btn-primary me-2">
                        <i class="fas fa-arrow-left"></i> Go Back
                    </a>
                    <a href="login.php" class="btn btn-outline-primary">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>