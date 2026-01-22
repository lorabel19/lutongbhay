<?php
// Excel Export Function (XLS format with design)
function exportToExcel($seller_id, $start_date, $end_date, $seller_name, $date_range, $conn) {
    // Get all data
    $summary_sql = "SELECT 
        COUNT(DISTINCT o.OrderID) as total_orders,
        COUNT(DISTINCT c.CustomerID) as total_customers,
        COALESCE(SUM(od.Subtotal), 0) as total_sales,
        COALESCE(AVG(od.Subtotal), 0) as average_order_value,
        COALESCE(SUM(od.Quantity), 0) as total_items_sold
        FROM `Order` o
        JOIN OrderDetails od ON o.OrderID = od.OrderID
        JOIN Meal m ON od.MealID = m.MealID
        JOIN Customer c ON o.CustomerID = c.CustomerID
        WHERE m.SellerID = ? 
        AND o.Status IN ('Confirmed', 'Completed')
        AND DATE(o.OrderDate) BETWEEN ? AND ?";
    
    $summary_stmt = $conn->prepare($summary_sql);
    $summary_stmt->bind_param("iss", $seller_id, $start_date, $end_date);
    $summary_stmt->execute();
    $summary_result = $summary_stmt->get_result();
    $summary = $summary_result->fetch_assoc();
    $summary_stmt->close();
    
    // Get category data
    $category_sql = "SELECT 
        m.Category,
        SUM(od.Quantity) as total_quantity,
        SUM(od.Subtotal) as total_sales
        FROM OrderDetails od
        JOIN Meal m ON od.MealID = m.MealID
        JOIN `Order` o ON od.OrderID = o.OrderID
        WHERE m.SellerID = ? 
        AND o.Status IN ('Confirmed', 'Completed')
        AND DATE(o.OrderDate) BETWEEN ? AND ?
        GROUP BY m.Category
        ORDER BY total_sales DESC";
    
    $category_stmt = $conn->prepare($category_sql);
    $category_stmt->bind_param("iss", $seller_id, $start_date, $end_date);
    $category_stmt->execute();
    $category_result = $category_stmt->get_result();
    
    // Get top meals - FIXED VERSION
    $top_meals_sql = "SELECT 
        m.Title,
        m.Category,
        COALESCE(m.Price, 0) as Price,
        SUM(od.Quantity) as total_quantity,
        SUM(od.Subtotal) as total_sales
        FROM OrderDetails od
        JOIN Meal m ON od.MealID = m.MealID
        JOIN `Order` o ON od.OrderID = o.OrderID
        WHERE m.SellerID = ? 
        AND o.Status IN ('Confirmed', 'Completed')
        AND DATE(o.OrderDate) BETWEEN ? AND ?
        GROUP BY m.MealID, m.Title, m.Category, m.Price
        ORDER BY total_sales DESC
        LIMIT 10";
    
    $top_meals_stmt = $conn->prepare($top_meals_sql);
    $top_meals_stmt->bind_param("iss", $seller_id, $start_date, $end_date);
    $top_meals_stmt->execute();
    $top_meals_result = $top_meals_stmt->get_result();
    
    // Get top customers
    $customer_sql = "SELECT 
        c.FullName,
        c.Email,
        COUNT(DISTINCT o.OrderID) as order_count,
        SUM(od.Subtotal) as total_spent,
        MAX(o.OrderDate) as last_order_date
        FROM Customer c
        JOIN `Order` o ON c.CustomerID = o.CustomerID
        JOIN OrderDetails od ON o.OrderID = od.OrderID
        JOIN Meal m ON od.MealID = m.MealID
        WHERE m.SellerID = ? 
        AND o.Status IN ('Confirmed', 'Completed')
        AND DATE(o.OrderDate) BETWEEN ? AND ?
        GROUP BY c.CustomerID
        ORDER BY total_spent DESC
        LIMIT 10";
    
    $customer_stmt = $conn->prepare($customer_sql);
    $customer_stmt->bind_param("iss", $seller_id, $start_date, $end_date);
    $customer_stmt->execute();
    $customer_result = $customer_stmt->get_result();
    
    // Create HTML content for Excel (with styling)
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <meta charset="UTF-8">
        <title>Sales Report - ' . htmlspecialchars($seller_name) . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #e63946; padding-bottom: 10px; }
            .header h1 { color: #e63946; margin: 0; font-size: 24px; }
            .header p { margin: 5px 0; font-size: 14px; }
            .section { margin-bottom: 25px; }
            .section h2 { color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 15px; font-size: 18px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 12px; }
            th { background-color: #e63946; color: white; text-align: left; padding: 10px; border: 1px solid #ddd; font-weight: bold; }
            td { padding: 8px; border: 1px solid #ddd; }
            .summary-box { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px; }
            .summary-item { background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #e63946; }
            .summary-item h3 { margin: 0 0 5px 0; font-size: 14px; color: #666; }
            .summary-item .value { font-size: 20px; font-weight: bold; color: #e63946; }
            .highlight { background-color: #fff3cd; font-weight: bold; }
            .currency { text-align: right; }
            .center { text-align: center; }
            .footer { margin-top: 40px; text-align: center; font-size: 11px; color: #666; padding-top: 20px; border-top: 1px solid #ddd; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>LUTONGBAHAY SALES REPORT</h1>
            <p><strong>Seller:</strong> ' . htmlspecialchars($seller_name) . '</p>
            <p><strong>Report Period:</strong> ' . htmlspecialchars($date_range) . '</p>
            <p><strong>Date Range:</strong> ' . $start_date . ' to ' . $end_date . '</p>
            <p><strong>Generated:</strong> ' . date('F j, Y, g:i A') . '</p>
        </div>
        
        <div class="section">
            <h2>SALES SUMMARY</h2>
            <div class="summary-box">
                <div class="summary-item">
                    <h3>Total Sales</h3>
                    <div class="value">₱' . number_format($summary['total_sales'] ?? 0, 2) . '</div>
                </div>
                <div class="summary-item">
                    <h3>Total Orders</h3>
                    <div class="value">' . number_format($summary['total_orders'] ?? 0) . '</div>
                </div>
                <div class="summary-item">
                    <h3>Total Customers</h3>
                    <div class="value">' . number_format($summary['total_customers'] ?? 0) . '</div>
                </div>
                <div class="summary-item">
                    <h3>Avg. Order Value</h3>
                    <div class="value">₱' . number_format($summary['average_order_value'] ?? 0, 2) . '</div>
                </div>
                <div class="summary-item">
                    <h3>Items Sold</h3>
                    <div class="value">' . number_format($summary['total_items_sold'] ?? 0) . '</div>
                </div>
            </div>
        </div>';
    
    // Category Performance
    $html .= '
        <div class="section">
            <h2>CATEGORY PERFORMANCE</h2>
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="center">Quantity Sold</th>
                        <th class="currency">Total Sales</th>
                        <th class="center">Percentage</th>
                    </tr>
                </thead>
                <tbody>';
    
    $total_sales = $summary['total_sales'] ?? 1;
    while($category = $category_result->fetch_assoc()) {
        $percentage = ($category['total_sales'] / $total_sales) * 100;
        $html .= '
                    <tr>
                        <td>' . htmlspecialchars($category['Category']) . '</td>
                        <td class="center">' . number_format($category['total_quantity']) . '</td>
                        <td class="currency">₱' . number_format($category['total_sales'], 2) . '</td>
                        <td class="center">' . number_format($percentage, 1) . '%</td>
                    </tr>';
    }
    $category_stmt->close();
    
    // Top Selling Meals
    $html .= '
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <h2>TOP SELLING MEALS</h2>
            <table>
                <thead>
                    <tr>
                        <th>Meal</th>
                        <th>Category</th>
                        <th class="currency">Price</th>
                        <th class="center">Quantity</th>
                        <th class="currency">Sales</th>
                    </tr>
                </thead>
                <tbody>';
    
    while($meal = $top_meals_result->fetch_assoc()) {
        $html .= '
                    <tr>
                        <td>' . htmlspecialchars($meal['Title']) . '</td>
                        <td>' . htmlspecialchars($meal['Category']) . '</td>
                        <td class="currency">₱' . number_format($meal['Price'] ?? 0, 2) . '</td>
                        <td class="center">' . number_format($meal['total_quantity']) . '</td>
                        <td class="currency highlight">₱' . number_format($meal['total_sales'], 2) . '</td>
                    </tr>';
    }
    $top_meals_stmt->close();
    
    // Top Customers
    $html .= '
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <h2>TOP CUSTOMERS</h2>
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Email</th>
                        <th class="center">Orders</th>
                        <th class="currency">Total Spent</th>
                        <th>Last Order</th>
                    </tr>
                </thead>
                <tbody>';
    
    while($customer = $customer_result->fetch_assoc()) {
        $html .= '
                    <tr>
                        <td>' . htmlspecialchars($customer['FullName']) . '</td>
                        <td>' . htmlspecialchars($customer['Email']) . '</td>
                        <td class="center">' . number_format($customer['order_count']) . '</td>
                        <td class="currency highlight">₱' . number_format($customer['total_spent'], 2) . '</td>
                        <td>' . date('M d, Y', strtotime($customer['last_order_date'])) . '</td>
                    </tr>';
    }
    $customer_stmt->close();
    
    $html .= '
                </tbody>
            </table>
        </div>
        
        <div class="footer">
            <p>This report was generated by LutongBahay Seller Portal</p>
            <p>Polytechnic University of the Philippines - Parañaque City Campus</p>
            <p>© ' . date('Y') . ' All rights reserved</p>
        </div>
    </body>
    </html>';
    
    // Output as Excel (HTML format that Excel can open)
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sales_report_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo $html;
    exit();
}

// PDF Export Function with TCPDF
function exportToPDF($seller_id, $start_date, $end_date, $seller_name, $date_range, $conn) {
    // Check if TCPDF exists
    $tcpdf_path = __DIR__ . '/TCPDF-main/tcpdf.php';
    if (!file_exists($tcpdf_path)) {
        // Show error message
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>TCPDF Required</title>
            <style>
                body { font-family: Arial; padding: 40px; background: #f5f5f5; }
                .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
                h1 { color: #e63946; }
                .steps { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .step { margin: 15px 0; padding: 10px; border-left: 4px solid #e63946; background: white; }
                .btn { display: inline-block; background: #e63946; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>📄 TCPDF Library Required for PDF Export</h1>
                <p>To enable PDF export functionality, please install TCPDF:</p>
                
                <div class="steps">
                    <div class="step">
                        <strong>Step 1:</strong> Download TCPDF from:<br>
                        <a href="https://github.com/tecnickcom/TCPDF/archive/master.zip" target="_blank">
                            https://github.com/tecnickcom/TCPDF/archive/master.zip
                        </a>
                    </div>
                    
                    <div class="step">
                        <strong>Step 2:</strong> Extract the downloaded ZIP file
                    </div>
                    
                    <div class="step">
                        <strong>Step 3:</strong> Rename the extracted folder to <code>tcpdf</code> and place it in:<br>
                        <code>C:\xampp\htdocs\lutongbahay\tcpdf\</code>
                    </div>
                    
                    <div class="step">
                        <strong>Step 4:</strong> The folder structure should look like this:<br>
                        <code>C:\xampp\htdocs\lutongbahay\tcpdf\tcpdf.php</code>
                    </div>
                </div>
                
                <p>After installation, the PDF export will work with professional tables like Excel.</p>
                
                <a href="seller-sales.php" class="btn">← Back to Sales Report</a>
            </div>
        </body>
        </html>';
        exit();
    }
    
    // Include TCPDF
    require_once($tcpdf_path);
    
    // Get all data for PDF
    $summary_sql = "SELECT 
        COUNT(DISTINCT o.OrderID) as total_orders,
        COUNT(DISTINCT c.CustomerID) as total_customers,
        COALESCE(SUM(od.Subtotal), 0) as total_sales,
        COALESCE(AVG(od.Subtotal), 0) as average_order_value,
        COALESCE(SUM(od.Quantity), 0) as total_items_sold
        FROM `Order` o
        JOIN OrderDetails od ON o.OrderID = od.OrderID
        JOIN Meal m ON od.MealID = m.MealID
        JOIN Customer c ON o.CustomerID = c.CustomerID
        WHERE m.SellerID = ? 
        AND o.Status IN ('Confirmed', 'Completed')
        AND DATE(o.OrderDate) BETWEEN ? AND ?";
    
    $summary_stmt = $conn->prepare($summary_sql);
    $summary_stmt->bind_param("iss", $seller_id, $start_date, $end_date);
    $summary_stmt->execute();
    $summary_result = $summary_stmt->get_result();
    $summary = $summary_result->fetch_assoc();
    $summary_stmt->close();
    
    // Get category data with order count
    $category_sql = "SELECT 
        m.Category,
        COUNT(DISTINCT o.OrderID) as order_count,
        SUM(od.Quantity) as total_quantity,
        SUM(od.Subtotal) as total_sales
        FROM OrderDetails od
        JOIN Meal m ON od.MealID = m.MealID
        JOIN `Order` o ON od.OrderID = o.OrderID
        WHERE m.SellerID = ? 
        AND o.Status IN ('Confirmed', 'Completed')
        AND DATE(o.OrderDate) BETWEEN ? AND ?
        GROUP BY m.Category
        ORDER BY total_sales DESC";
    
    $category_stmt = $conn->prepare($category_sql);
    $category_stmt->bind_param("iss", $seller_id, $start_date, $end_date);
    $category_stmt->execute();
    $category_result = $category_stmt->get_result();
    
    // Get top meals - FIXED VERSION
    $top_meals_sql = "SELECT 
        m.Title,
        m.Category,
        COALESCE(m.Price, 0) as Price,
        COUNT(od.OrderDetailID) as times_ordered,
        SUM(od.Quantity) as total_quantity,
        SUM(od.Subtotal) as total_sales
        FROM OrderDetails od
        JOIN Meal m ON od.MealID = m.MealID
        JOIN `Order` o ON od.OrderID = o.OrderID
        WHERE m.SellerID = ? 
        AND o.Status IN ('Confirmed', 'Completed')
        AND DATE(o.OrderDate) BETWEEN ? AND ?
        GROUP BY m.MealID, m.Title, m.Category, m.Price
        ORDER BY total_sales DESC
        LIMIT 10";
    
    $top_meals_stmt = $conn->prepare($top_meals_sql);
    $top_meals_stmt->bind_param("iss", $seller_id, $start_date, $end_date);
    $top_meals_stmt->execute();
    $top_meals_result = $top_meals_stmt->get_result();
    
    // Get top customers
    $customer_sql = "SELECT 
        c.FullName,
        c.Email,
        COUNT(DISTINCT o.OrderID) as order_count,
        SUM(od.Subtotal) as total_spent,
        MAX(o.OrderDate) as last_order_date
        FROM Customer c
        JOIN `Order` o ON c.CustomerID = o.CustomerID
        JOIN OrderDetails od ON o.OrderID = od.OrderID
        JOIN Meal m ON od.MealID = m.MealID
        WHERE m.SellerID = ? 
        AND o.Status IN ('Confirmed', 'Completed')
        AND DATE(o.OrderDate) BETWEEN ? AND ?
        GROUP BY c.CustomerID
        ORDER BY total_spent DESC
        LIMIT 10";
    
    $customer_stmt = $conn->prepare($customer_sql);
    $customer_stmt->bind_param("iss", $seller_id, $start_date, $end_date);
    $customer_stmt->execute();
    $customer_result = $customer_stmt->get_result();
    
    // Create PDF with TCPDF
    createPDFWithTCPDF($seller_name, $date_range, $start_date, $end_date, $summary, $category_result, $top_meals_result, $customer_result);
    exit();
}

// Function to create PDF using TCPDF
function createPDFWithTCPDF($seller_name, $date_range, $start_date, $end_date, $summary, $category_result, $top_meals_result, $customer_result) {
    // Create new PDF document
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('LutongBahay Seller Portal');
    $pdf->SetAuthor('LutongBahay');
    $pdf->SetTitle('Sales Report - ' . $seller_name);
    $pdf->SetSubject('Sales Report');
    $pdf->SetKeywords('Sales, Report, LutongBahay');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Colors
    $primary_color = array(230, 57, 70); // LutongBahay red
    $light_gray = array(240, 240, 240);
    $dark_gray = array(100, 100, 100);
    
    // **FIX 1: Use "PHP" instead of Peso sign for compatibility**
    $peso_prefix = "PHP ";
    
    // **FIX 2: OR use simple "P"**
    // $peso_prefix = "P ";
    
    // **FIX 3: OR try with Unicode (for newer TCPDF)**
    // Try this if PHP doesn't work:
    // $peso_sign = "\xe2\x82\xb1"; // UTF-8 encoding of ₱
    
    // ===== HEADER SECTION =====
    // Try different fonts that might support Peso sign
    try {
        // Try DejaVu font first (supports Peso sign)
        $pdf->SetFont('dejavusans', 'B', 20);
    } catch (Exception $e) {
        // Fallback to helvetica
        $pdf->SetFont('helvetica', 'B', 20);
    }
    
    $pdf->SetTextColor($primary_color[0], $primary_color[1], $primary_color[2]);
    $pdf->Cell(0, 10, 'LUTONGBAHAY SALES REPORT', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Seller Info
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 6, 'Seller: ' . $seller_name, 0, 1);
    $pdf->Cell(0, 6, 'Report Period: ' . $date_range, 0, 1);
    $pdf->Cell(0, 6, 'Date Range: ' . $start_date . ' to ' . $end_date, 0, 1);
    $pdf->Cell(0, 6, 'Generated: ' . date('F j, Y, g:i A'), 0, 1);
    
    // Line separator
    $pdf->Ln(5);
    $pdf->SetDrawColor($primary_color[0], $primary_color[1], $primary_color[2]);
    $pdf->SetLineWidth(0.5);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(10);
    
    // ===== SALES SUMMARY TABLE =====
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor($primary_color[0], $primary_color[1], $primary_color[2]);
    $pdf->Cell(0, 10, 'SALES SUMMARY', 0, 1);
    $pdf->Ln(3);
    
    // Summary table data - USE PHP instead of Peso sign
    $summary_data = array(
        array('Total Sales', $peso_prefix . number_format($summary['total_sales'] ?? 0, 2)),
        array('Total Orders', number_format($summary['total_orders'] ?? 0)),
        array('Total Customers', number_format($summary['total_customers'] ?? 0)),
        array('Average Order Value', $peso_prefix . number_format($summary['average_order_value'] ?? 0, 2)),
        array('Total Items Sold', number_format($summary['total_items_sold'] ?? 0))
    );
    
    // Create summary table
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor($light_gray[0], $light_gray[1], $light_gray[2]);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->SetLineWidth(0.2);
    
    $col_width = array(100, 80); // Column widths
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor($primary_color[0], $primary_color[1], $primary_color[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($col_width[0], 8, 'Metric', 1, 0, 'L', true);
    $pdf->Cell($col_width[1], 8, 'Value', 1, 1, 'R', true);
    
    // Table data
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    $row_count = 0;
    
    foreach ($summary_data as $row) {
        $fill = ($row_count % 2 == 0) ? array(255, 255, 255) : array(248, 249, 250);
        $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
        
        $pdf->Cell($col_width[0], 8, $row[0], 'LR', 0, 'L', true);
        $pdf->Cell($col_width[1], 8, $row[1], 'LR', 1, 'R', true);
        $row_count++;
    }
    
    // Close table
    $pdf->Cell(array_sum($col_width), 0, '', 'T');
    $pdf->Ln(15);
    
    // ===== CATEGORY PERFORMANCE TABLE =====
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor($primary_color[0], $primary_color[1], $primary_color[2]);
    $pdf->Cell(0, 10, 'CATEGORY PERFORMANCE', 0, 1);
    $pdf->Ln(3);
    
    // Category table header
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor($primary_color[0], $primary_color[1], $primary_color[2]);
    $pdf->SetTextColor(255, 255, 255);
    
    $cat_cols = array(70, 30, 30, 40); // Column widths
    $cat_headers = array('Category', 'Orders', 'Qty', 'Sales');
    
    for ($i = 0; $i < count($cat_headers); $i++) {
        $pdf->Cell($cat_cols[$i], 8, $cat_headers[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    
    // Category data - USE PHP instead of Peso sign
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $row_count = 0;
    $total_sales = $summary['total_sales'] ?? 1;
    
    while($category = $category_result->fetch_assoc()) {
        $percentage = ($category['total_sales'] / $total_sales) * 100;
        $fill = ($row_count % 2 == 0) ? array(255, 255, 255) : array(248, 249, 250);
        $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
        
        $pdf->Cell($cat_cols[0], 8, $category['Category'], 'LR', 0, 'L', true);
        $pdf->Cell($cat_cols[1], 8, number_format($category['order_count']), 'LR', 0, 'C', true);
        $pdf->Cell($cat_cols[2], 8, number_format($category['total_quantity']), 'LR', 0, 'C', true);
        $pdf->Cell($cat_cols[3], 8, $peso_prefix . number_format($category['total_sales'], 2), 'LR', 1, 'R', true);
        
        $row_count++;
    }
    
    // Close table
    $pdf->Cell(array_sum($cat_cols), 0, '', 'T');
    $pdf->Ln(15);
    
    // Check if we need new page
    if ($pdf->GetY() > 220) {
        $pdf->AddPage();
    }
    
    // ===== TOP SELLING MEALS TABLE =====
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor($primary_color[0], $primary_color[1], $primary_color[2]);
    $pdf->Cell(0, 10, 'TOP SELLING MEALS', 0, 1);
    $pdf->Ln(3);
    
    // Meals table header
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor($primary_color[0], $primary_color[1], $primary_color[2]);
    $pdf->SetTextColor(255, 255, 255);
    
    $meal_cols = array(15, 65, 40, 30, 20, 20); // Column widths
    $meal_headers = array('#', 'Meal', 'Category', 'Price', 'Qty', 'Sales');
    
    for ($i = 0; $i < count($meal_headers); $i++) {
        $pdf->Cell($meal_cols[$i], 8, $meal_headers[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    
    // Meals data - USE PHP instead of Peso sign
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $row_count = 0;
    $rank = 1;
    
    while($meal = $top_meals_result->fetch_assoc()) {
        // Highlight top 3
        if ($rank <= 3) {
            $pdf->SetFillColor(255, 243, 205); // Light yellow
        } else {
            $fill = ($row_count % 2 == 0) ? array(255, 255, 255) : array(248, 249, 250);
            $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
        }
        
        $pdf->Cell($meal_cols[0], 8, $rank, 'LR', 0, 'C', true);
        $pdf->Cell($meal_cols[1], 8, substr($meal['Title'], 0, 25), 'LR', 0, 'L', true);
        $pdf->Cell($meal_cols[2], 8, $meal['Category'], 'LR', 0, 'C', true);
        $pdf->Cell($meal_cols[3], 8, $peso_prefix . number_format($meal['Price'] ?? 0, 2), 'LR', 0, 'R', true);
        $pdf->Cell($meal_cols[4], 8, number_format($meal['total_quantity']), 'LR', 0, 'C', true);
        $pdf->Cell($meal_cols[5], 8, $peso_prefix . number_format($meal['total_sales'], 2), 'LR', 1, 'R', true);
        
        $rank++;
        $row_count++;
    }
    
    // Close table
    $pdf->Cell(array_sum($meal_cols), 0, '', 'T');
    $pdf->Ln(15);
    
    // Check if we need new page for customers
    if ($pdf->GetY() > 200) {
        $pdf->AddPage();
    }
    
    // ===== TOP CUSTOMERS TABLE =====
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor($primary_color[0], $primary_color[1], $primary_color[2]);
    $pdf->Cell(0, 10, 'TOP CUSTOMERS', 0, 1);
    $pdf->Ln(3);
    
    // Customers table header
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor($primary_color[0], $primary_color[1], $primary_color[2]);
    $pdf->SetTextColor(255, 255, 255);
    
    $cust_cols = array(15, 60, 60, 25, 30); // Column widths
    $cust_headers = array('#', 'Customer', 'Email', 'Orders', 'Total Spent');
    
    for ($i = 0; $i < count($cust_headers); $i++) {
        $pdf->Cell($cust_cols[$i], 8, $cust_headers[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    
    // Customers data - USE PHP instead of Peso sign
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $row_count = 0;
    $rank = 1;
    
    while($customer = $customer_result->fetch_assoc()) {
        // Highlight top 3
        if ($rank <= 3) {
            $pdf->SetFillColor(255, 243, 205); // Light yellow
        } else {
            $fill = ($row_count % 2 == 0) ? array(255, 255, 255) : array(248, 249, 250);
            $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
        }
        
        $pdf->Cell($cust_cols[0], 8, $rank, 'LR', 0, 'C', true);
        $pdf->Cell($cust_cols[1], 8, substr($customer['FullName'], 0, 25), 'LR', 0, 'L', true);
        $pdf->Cell($cust_cols[2], 8, substr($customer['Email'], 0, 25), 'LR', 0, 'L', true);
        $pdf->Cell($cust_cols[3], 8, number_format($customer['order_count']), 'LR', 0, 'C', true);
        $pdf->Cell($cust_cols[4], 8, $peso_prefix . number_format($customer['total_spent'], 2), 'LR', 1, 'R', true);
        
        $rank++;
        $row_count++;
    }
    
    // Close table
    $pdf->Cell(array_sum($cust_cols), 0, '', 'T');
    $pdf->Ln(20);
    
    // ===== FOOTER =====
    $pdf->SetY(-40);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor($dark_gray[0], $dark_gray[1], $dark_gray[2]);
    
    $pdf->Cell(0, 5, 'Generated by LutongBahay Seller Portal', 0, 1, 'C');
    $pdf->Cell(0, 5, 'Polytechnic University of the Philippines - Parañaque City Campus', 0, 1, 'C');
    $pdf->Cell(0, 5, '© ' . date('Y') . ' All rights reserved | Report ID: LB-' . date('Ymd-His'), 0, 1, 'C');
    
    // Line above footer
    $pdf->SetY(-45);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->SetLineWidth(0.3);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    
    // Output PDF
    $pdf->Output('sales_report_' . date('Y-m-d') . '.pdf', 'D');
    exit();
}
?>