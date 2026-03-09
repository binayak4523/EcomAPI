-- Create product_offers table for storing product discount offers
CREATE TABLE IF NOT EXISTS product_offers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    discount_percentage INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES item_master(ID) ON DELETE CASCADE
);
