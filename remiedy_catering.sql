CREATE DATABASE IF NOT EXISTS remiedy_catering;
USE remiedy_catering;

CREATE TABLE users (
  id int(11) NOT NULL AUTO_INCREMENT,
  username varchar(100) NOT NULL,
  password varchar(255) NOT NULL,
  name varchar(100) NOT NULL,
  email varchar(100) NOT NULL,
  phone varchar(20) DEFAULT NULL,
  address text DEFAULT NULL,
  role enum('admin','customer') NOT NULL DEFAULT 'customer',
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY username (username),
  UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (username, password, name, email, phone, role) VALUES
('admin', '$2y$10$qoqELbr5mHsRYGYqQDYbq.TVa.jYM0SFZ0ZmO4J4cdrOc59vw1.8W', 'Admin', 'admin@remiedy.com', '+63 9461434687', 'admin');

CREATE TABLE categories (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  description text DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO categories (name, description) VALUES
('Main Course', 'Delicious main dishes for your event'),
('Appetizers', 'Start your meal with these delightful treats'),
('Desserts', 'Sweet endings to your perfect meal'),
('Beverages', 'Refreshing drinks for all occasions'),
('Party Packages', 'Complete catering packages for events');

CREATE TABLE food_items (
  id int(11) NOT NULL AUTO_INCREMENT,
  category_id int(11) NOT NULL,
  name varchar(100) NOT NULL,
  description text DEFAULT NULL,
  price decimal(10,2) NOT NULL,
  image varchar(255) DEFAULT 'default_food.jpg',
  is_available tinyint(1) NOT NULL DEFAULT 1,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY category_id (category_id),
  CONSTRAINT food_items_ibfk_1 FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO food_items (category_id, name, description, price, image, is_available) VALUES
(1, 'Garlic Buttered Shrimp', 'Succulent shrimps cooked in garlic butter sauce', 450.00, 'shrimp.jpg', 1),
(1, 'Filipino Pork BBQ', 'Traditional Filipino-style pork barbecue with sweet and savory glaze', 350.00, 'pork_bbq.jpg', 1),
(1, 'Crispy Fried Chicken', 'Crispy on the outside, juicy on the inside fried chicken', 300.00, 'fried_chicken.jpg', 1),
(2, 'Lumpiang Shanghai', 'Filipino spring rolls filled with seasoned ground pork', 200.00, 'lumpia.jpg', 1),
(2, 'Cheese Sticks', 'Golden fried cheese sticks with dipping sauce', 180.00, 'cheese_sticks.jpg', 1),
(3, 'Leche Flan', 'Classic Filipino caramel custard dessert', 150.00, 'leche_flan.jpg', 1),
(3, 'Biko (Sweet Sticky Rice)', 'Traditional Filipino sweet sticky rice cake with coconut', 120.00, 'biko.jpg', 1),
(4, 'Fresh Buko Juice', 'Refreshing coconut water served with coconut meat', 80.00, 'buko_juice.jpg', 1),
(5, 'Basic Event Package', 'Package includes 3 main dishes, 2 appetizers, 1 dessert, and 1 beverage. Good for 30 people.', 8500.00, 'package_basic.jpg', 1),
(5, 'Premium Event Package', 'Package includes 5 main dishes, 3 appetizers, 2 desserts, and 2 beverages. Good for 50 people.', 15000.00, 'package_premium.jpg', 1);

CREATE TABLE orders (
  id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL,
  event_date date NOT NULL,
  event_time time NOT NULL,
  event_location text NOT NULL,
  guest_count int(11) NOT NULL,
  special_requests text DEFAULT NULL,
  subtotal decimal(10,2) NOT NULL,
  service_fee decimal(10,2) NOT NULL,
  total_amount decimal(10,2) NOT NULL,
  status enum('pending','confirmed','preparing','delivered','completed','cancelled') NOT NULL DEFAULT 'pending',
  payment_status enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY user_id (user_id),
  CONSTRAINT orders_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_items (
  id int(11) NOT NULL AUTO_INCREMENT,
  order_id int(11) NOT NULL,
  food_id int(11) NOT NULL,
  quantity int(11) NOT NULL,
  price decimal(10,2) NOT NULL,
  subtotal decimal(10,2) NOT NULL,
  PRIMARY KEY (id),
  KEY order_id (order_id),
  KEY food_id (food_id),
  CONSTRAINT order_items_ibfk_1 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT order_items_ibfk_2 FOREIGN KEY (food_id) REFERENCES food_items (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;