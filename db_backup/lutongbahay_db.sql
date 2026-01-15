use lutongbahay_db;




-- SELLER TABLE
CREATE TABLE Seller (
    SellerID INT AUTO_INCREMENT PRIMARY KEY,
    Username VARCHAR(50) NOT NULL UNIQUE,
    Email VARCHAR(150) NOT NULL UNIQUE,
    Password VARCHAR(255) NOT NULL,
    FullName VARCHAR(100) NOT NULL,
    ContactNo VARCHAR(20),
    Address VARCHAR(255),
    ImagePath VARCHAR(500), -- Profile picture
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- CUSTOMER TABLE
CREATE TABLE Customer (
    CustomerID INT AUTO_INCREMENT PRIMARY KEY,
    Username VARCHAR(50) NOT NULL UNIQUE,
    Email VARCHAR(150) NOT NULL UNIQUE,
    Password VARCHAR(255) NOT NULL,
    FullName VARCHAR(100) NOT NULL,
    ContactNo VARCHAR(20),
    Address VARCHAR(255),
    ImagePath VARCHAR(500), -- Profile picture
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- MEAL TABLE
CREATE TABLE Meal (
    MealID INT AUTO_INCREMENT PRIMARY KEY,
    SellerID INT NOT NULL,
    Title VARCHAR(100) NOT NULL,
    Description TEXT,
    Price DECIMAL(10,2) NOT NULL,
    ImagePath VARCHAR(500),
    Availability ENUM('Available','Not Available') DEFAULT 'Available',
    Category ENUM(
        'Main Dishes',
        'Desserts',
        'Merienda',
        'Vegetarian',
        'Holiday Specials'
    ) NOT NULL DEFAULT 'Main Dishes',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (SellerID) REFERENCES Seller(SellerID)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- CART TABLE
CREATE TABLE Cart (
    CartID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT NOT NULL,
    MealID INT NOT NULL,
    Quantity INT NOT NULL DEFAULT 1,
    AddedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (CustomerID) REFERENCES Customer(CustomerID)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (MealID) REFERENCES Meal(MealID)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ORDER TABLE
CREATE TABLE `Order` (
    OrderID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT NOT NULL,
    OrderDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    Status ENUM('Pending','Confirmed', 'Preparing',
    'Out for Delivery', 'Completed','Cancelled') DEFAULT 'Pending',
    TotalAmount DECIMAL(10,2) DEFAULT 0,
    DeliveryAddress VARCHAR(255),
    ContactNo VARCHAR(15),
    Notes TEXT,
    FOREIGN KEY (CustomerID) REFERENCES Customer(CustomerID)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ORDER DETAILS TABLE
CREATE TABLE OrderDetails (
    OrderDetailID INT AUTO_INCREMENT PRIMARY KEY,
    OrderID INT NOT NULL,
    MealID INT NOT NULL,
    Quantity INT NOT NULL DEFAULT 1,
    Subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (OrderID) REFERENCES `Order`(OrderID)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (MealID) REFERENCES Meal(MealID)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- INDEXES
CREATE INDEX idx_meal_category ON Meal(Category);
CREATE INDEX idx_meal_seller ON Meal(SellerID);
