USE event_ticket_system;

INSERT INTO Roles (Role_Name, Description) VALUES
('Admin',     'Full system access: manage staff accounts, all events, and reports'),
('Organizer', 'Create and manage their own events, ticket tiers, and view sales'),
('Staff',     'Scan/verify QR tickets at the door and view attendee lists');


INSERT INTO Staff (Staff_User, Password_Hash, First_Name, Last_Name, Email, Role_ID) VALUES
('admin',      '$2y$10$R9mbP3y0TzCtWtWPWpa83Ox1EqGPDetPDGPlkr7q06qUIQum81bX2', 'Andrea',  'Reyes',   'admin@ticketstub.test', 1),
('organizer1', '$2y$10$R9mbP3y0TzCtWtWPWpa83Ox1EqGPDetPDGPlkr7q06qUIQum81bX2', 'Marco',   'Dela Cruz','organizer@ticketstub.test', 2),
('staff1',     '$2y$10$R9mbP3y0TzCtWtWPWpa83Ox1EqGPDetPDGPlkr7q06qUIQum81bX2', 'Bea',     'Santos',  'staff@ticketstub.test', 3);


INSERT INTO Customers (Customer_User, Password_Hash, First_Name, Last_Name, Email_Address, Phone_Number) VALUES
('juandelacruz', '$2y$10$R9mbP3y0TzCtWtWPWpa83Ox1EqGPDetPDGPlkr7q06qUIQum81bX2', 'Juan', 'Dela Cruz', 'juan@example.com', '09171234567');


INSERT INTO Venues (Venue_Name, Address, Max_Capacity) VALUES
('The Amber Hall', '123 Diversion Road, Cebu City',  200),
('Skyline Grounds', '45 Bayfront Ave, Cebu City',     500);


INSERT INTO Seats (Venue_ID, Seat_Row, Seat_Number, Section_Label, Pos_X, Pos_Y) VALUES
(1,'A',1,'Orchestra',0,0),(1,'A',2,'Orchestra',1,0),(1,'A',3,'Orchestra',2,0),(1,'A',4,'Orchestra',3,0),(1,'A',5,'Orchestra',4,0),
(1,'A',6,'Orchestra',7,0),(1,'A',7,'Orchestra',8,0),(1,'A',8,'Orchestra',9,0),(1,'A',9,'Orchestra',10,0),(1,'A',10,'Orchestra',11,0),
(1,'B',1,'Orchestra',0,1),(1,'B',2,'Orchestra',1,1),(1,'B',3,'Orchestra',2,1),(1,'B',4,'Orchestra',3,1),(1,'B',5,'Orchestra',4,1),
(1,'B',6,'Orchestra',7,1),(1,'B',7,'Orchestra',8,1),(1,'B',8,'Orchestra',9,1),(1,'B',9,'Orchestra',10,1),(1,'B',10,'Orchestra',11,1),
(1,'C',1,'Orchestra',0,2),(1,'C',2,'Orchestra',1,2),(1,'C',3,'Orchestra',2,2),(1,'C',4,'Orchestra',3,2),(1,'C',5,'Orchestra',4,2),
(1,'C',6,'Orchestra',7,2),(1,'C',7,'Orchestra',8,2),(1,'C',8,'Orchestra',9,2),(1,'C',9,'Orchestra',10,2),(1,'C',10,'Orchestra',11,2),
(1,'D',1,'Orchestra',0,3),(1,'D',2,'Orchestra',1,3),(1,'D',3,'Orchestra',2,3),(1,'D',4,'Orchestra',3,3),(1,'D',5,'Orchestra',4,3),
(1,'D',6,'Orchestra',7,3),(1,'D',7,'Orchestra',8,3),(1,'D',8,'Orchestra',9,3),(1,'D',9,'Orchestra',10,3),(1,'D',10,'Orchestra',11,3);


INSERT INTO Events (Venue_ID, Staff_ID, Event_Name, Event_Description, Category, Start_Date_Time, End_Date_Time, Event_Status) VALUES
(1, 2, 'Afterglow: An Indie Night', 'A curated night of local indie and alternative acts closing out the summer season, featuring three headline bands and an open-air acoustic set.', 'Concert', '2026-08-14 19:00:00', '2026-08-14 23:30:00', 'published'),
(2, 2, 'Sunset Run Festival 5K', 'A community fun run followed by a live DJ set and food stalls along the bayfront at golden hour.', 'Sports', '2026-08-22 16:00:00', '2026-08-22 20:00:00', 'published'),
(1, 2, 'Comedy Loft: Live Stand-Up', 'An intimate stand-up comedy showcase featuring four rising comics from the local circuit.', 'Comedy', '2026-09-05 20:00:00', '2026-09-05 22:00:00', 'published');


INSERT INTO Ticket_Tiers (Event_ID, Tier_Name, Price, Quantity_Available) VALUES
(1, 'VIP (Reserved Seating)', 1500.00, 40),
(1, 'General Admission',       650.00, 160),
(2, 'General Admission',       350.00, 300),
(3, 'VIP (Reserved Seating)',   900.00, 40),
(3, 'General Admission',        400.00, 60);
