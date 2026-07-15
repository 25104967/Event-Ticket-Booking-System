<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['Admin', 'Organizer']);

$db = get_db();
$role = current_role();

$params = [];
$scope_sql = '';
if ($role === 'Organizer') { $scope_sql = 'WHERE e.Staff_ID = ?'; $params[] = $_SESSION['staff_id']; }

$stmt = $db->prepare(
    "SELECT e.Event_Name, v.Venue_Name, e.Start_Date_Time, e.Event_Status,
            COUNT(b.Booking_ID) AS Tickets_Sold,
            (SELECT COALESCE(SUM(t3.Amount_Paid), 0) FROM Transactions t3
             WHERE t3.Transaction_ID IN (
                 SELECT DISTINCT b3.Transaction_ID
                 FROM Bookings b3
                 JOIN Ticket_Tiers tt3 ON tt3.Tier_ID = b3.Tier_ID
                 WHERE tt3.Event_ID = e.Event_ID
                   AND b3.Booking_Status IN ('confirmed','used')
                   AND b3.Transaction_ID IS NOT NULL
             )) AS Revenue
     FROM Events e
     JOIN Venues v ON v.Venue_ID = e.Venue_ID
     LEFT JOIN Ticket_Tiers tt ON tt.Event_ID = e.Event_ID
     LEFT JOIN Bookings b ON b.Tier_ID = tt.Tier_ID AND b.Booking_Status IN ('confirmed','used')
     $scope_sql
     GROUP BY e.Event_ID, e.Event_Name, v.Venue_Name, e.Start_Date_Time, e.Event_Status
     ORDER BY e.Start_Date_Time DESC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filename = 'sales-report-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Event', 'Venue', 'Date', 'Status', 'Tickets Sold', 'Revenue (PHP)']);

$total_tickets = 0;
$total_revenue = 0.0;
foreach ($rows as $r) {
    fputcsv($out, [
        $r['Event_Name'],
        $r['Venue_Name'],
        $r['Start_Date_Time'],
        $r['Event_Status'],
        $r['Tickets_Sold'],
        number_format((float)$r['Revenue'], 2),
    ]);
    $total_tickets += (int)$r['Tickets_Sold'];
    $total_revenue += (float)$r['Revenue'];
}
fputcsv($out, []);
fputcsv($out, ['TOTAL', '', '', '', $total_tickets, number_format($total_revenue, 2)]);
fclose($out);
