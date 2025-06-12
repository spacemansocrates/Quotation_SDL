<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Dashboard</title>
    <style>
        /* Import a clean, professional font */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        :root {
            --bg-color: #111827; /* Dark Navy/Charcoal */
            --card-color: #1F2937; /* Slightly Lighter Dark Blue */
            --border-color: #374151; /* Dark Gray */
            --text-primary: #F9FAFB; /* Off-White */
            --text-secondary: #D1D5DB; /* Light Gray */
            --accent-color: #3B82F6; /* Professional Blue */
        }

        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            background-color: var(--bg-color);
            color: var(--text-primary);
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        .page-header {
            width: 100%;
            padding: 1rem 2rem;
            border-bottom: 1px solid var(--border-color);
            background-color: var(--bg-color);
            display: flex;
            justify-content: center; /* Center the content within the header */
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 1rem; /* Space between logo and title */
            width: 100%;
            max-width: 1200px; /* Match max-width of dashboard for alignment */
        }

        /* NEW: Styling for the logo image */
        .header-logo {
            height: 40px; /* Control the size of the logo */
            width: auto;
            border-radius: 6px; /* Softly rounded corners for the logo's white background */
            padding: 4px; /* Optional: adds a little space inside the border */
            background-color: white; /* Ensures the area behind the logo is clean white */
            border: 1px solid var(--border-color); /* A subtle frame */
        }

        .header-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
        }

        .dashboard-container {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            text-align: center;
            width: 100%;
            max-width: 1200px;
        }

        .welcome-message h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .welcome-message p {
            font-size: 1.1rem;
            font-weight: 400;
            color: var(--text-secondary);
            margin-bottom: 3rem;
            max-width: 600px;
        }

        .card-container {
            display: flex;
            justify-content: center;
            align-items: stretch;
            flex-wrap: wrap;
            gap: 2rem;
        }

        .card {
            width: 280px;
            padding: 2rem;
            text-decoration: none;
            color: var(--text-primary);
            background: var(--card-color);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: transform 0.2s ease-out, border-color 0.2s ease-out, box-shadow 0.2s ease-out;
        }

        .card:hover {
            transform: translateY(-5px);
            border-color: var(--accent-color);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .card-icon {
            width: 50px;
            height: 50px;
            margin-bottom: 1.5rem;
            color: #9CA3AF;
            transition: color 0.2s ease-out;
        }

        .card:hover .card-icon {
            color: var(--accent-color);
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0 0 0.5rem 0;
        }

        .card-description {
            font-size: 0.9rem;
            font-weight: 400;
            color: var(--text-secondary);
            line-height: 1.6;
            flex-grow: 1;
        }
        
        .card-link {
            margin-top: 1.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-secondary);
            transition: color 0.2s ease-out;
        }
        
        .card:hover .card-link {
            color: var(--accent-color);
        }

    </style>
</head>
<body>

    <header class="page-header">
        <div class="header-content">
            <img src="admin/images/logo.png" alt="Company Logo" class="header-logo">
            <h1 class="header-title">System Portal</h1>
        </div>
    </header>

    <main class="dashboard-container">
        <div class="welcome-message">
            <h2>Welcome Back</h2>
            <p>Select a module below to manage your business operations efficiently.</p>
        </div>

        <nav class="card-container">

            <a href="ini/admin_invoices.php" class="card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <h3 class="card-title">Invoices</h3>
                <p class="card-description">View, create, and manage all client invoices and payment records.</p>
                <span class="card-link">Go to Invoices &rarr;</span>
            </a>

            <a href="admin/admin_quotations.php" class="card">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                </div>
                <h3 class="card-title">Quotations</h3>
                <p class="card-description">Draft, send, and track the status of all customer quotations.</p>
                <span class="card-link">Go to Quotations &rarr;</span>
            </a>

            <a href="admin/inventory/" class="card">
                 <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                    </svg>
                </div>
                <h3 class="card-title">Inventory</h3>
                <p class="card-description">Manage your product stock, view levels, and organize your items.</p>
                <span class="card-link">Go to Inventory &rarr;</span>
            </a>
            
        </nav>
    </main>

</body>
</html>