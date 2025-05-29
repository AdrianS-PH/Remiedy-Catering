<?php
session_start();

// ============================
// Database Connection (PDO)
// ============================
$host = 'localhost';
$dbname = 'remiedy_catering';
$db_user = 'root';
$db_pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// ============================
// Helper Functions
// ============================
function clean_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

function redirect($url) {
    header("Location: $url");
    exit;
}

// For simplicity, we store the shopping cart in session as an associative array
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ============================
// Routing – Determine which page to show
// ============================
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// --------------------------
// Process Form Submissions
// --------------------------

// Process Order Submission
if ($page == 'process_order' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect order details
    $customerName    = clean_input($_POST['customerName']);
    $customerPhone   = clean_input($_POST['customerPhone']);
    $customerEmail   = clean_input($_POST['customerEmail']);
    $eventDate       = $_POST['eventDate'];
    $eventLocation   = clean_input($_POST['eventLocation']);
    $guestCount      = (int) $_POST['guestCount'];
    $specialRequests = clean_input($_POST['specialRequests']);
    
    // Calculate subtotal, service fee and total from session cart
    $subtotal = 0;
    foreach ($_SESSION['cart'] as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    $serviceFee = $subtotal * 0.10;
    $total = $subtotal + $serviceFee;
    
    try {
        // Insert order
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, event_date, event_time, event_location, guest_count, special_requests, subtotal, service_fee, total_amount, status, payment_status) VALUES (?,?,?,?,?, ?, ?, ?, ?, 'pending','unpaid')");
        // For customers not logged in, we set user_id to 0 (or you may force registration/login)
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
        $stmt->execute([$user_id, $eventDate, date("H:i:s"), $eventLocation, $guestCount, $specialRequests, $subtotal, $serviceFee, $total]);
        $order_id = $pdo->lastInsertId();
        
        // Insert each order item
        foreach ($_SESSION['cart'] as $item) {
            $item_subtotal = $item['price'] * $item['quantity'];
            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, food_id, quantity, price, subtotal) VALUES (?,?,?,?,?)");
            $stmt->execute([$order_id, $item['id'], $item['quantity'], $item['price'], $item_subtotal]);
        }
        // Clear cart after successful order
        $_SESSION['cart'] = [];
        redirect("index.php?page=order_confirmation&order_id=$order_id");
    } catch (Exception $e) {
        die("Order Processing Error: " . $e->getMessage());
    }
}

// Process Add to Cart (from menu or food details)
if (isset($_GET['action']) && $_GET['action'] == 'add_to_cart' && isset($_GET['id'])) {
    $food_id = (int) $_GET['id'];
    // Fetch food details
    $stmt = $pdo->prepare("SELECT * FROM food_items WHERE id = ?");
    $stmt->execute([$food_id]);
    $food = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($food) {
        if (isset($_SESSION['cart'][$food_id])) {
            $_SESSION['cart'][$food_id]['quantity']++;
        } else {
            $_SESSION['cart'][$food_id] = [
                'id'       => $food['id'],
                'name'     => $food['name'],
                'price'    => $food['price'],
                'quantity' => 1
            ];
        }
    }
    redirect("index.php?page=menu");
}

// Process Remove from Cart
if (isset($_GET['action']) && $_GET['action'] == 'remove_from_cart' && isset($_GET['id'])) {
    $food_id = (int) $_GET['id'];
    unset($_SESSION['cart'][$food_id]);
    redirect("index.php?page=cart");
}

// Process Admin Login
if ($page == 'admin_login' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $adminUsername = clean_input($_POST['adminUsername']);
    $adminPassword = $_POST['adminPassword'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin'");
    $stmt->execute([$adminUsername]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin && password_verify($adminPassword, $admin['password'])) {
        $_SESSION['admin_logged_in'] = true;
        redirect("index.php?page=admin_dashboard");
    } else {
        $admin_error = "Invalid admin credentials.";
    }
}

// Process Admin Logout
if ($page == 'admin_logout') {
    unset($_SESSION['admin_logged_in']);
    redirect("index.php?page=home");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Remiedy Event Catering Services</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    body { background-color: #f5f2ea; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .logo-circle { width: 120px; height: 120px; background-color: #666; border-radius: 50%; position: relative; border: 4px solid #fff; }
    .logo-utensil { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 2.5rem; }
    .hidden-section { display: none; }
    .custom-alert { display: none; position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 5px; z-index: 100; }
  </style>
</head>
<body>
<!-- HEADER -->
<header class="bg-white shadow-md">
  <div class="container mx-auto px-4 py-4 flex flex-col md:flex-row items-center justify-between">
    <div class="flex items-center mb-4 md:mb-0">
      <div class="logo-circle mr-3">
        <i class="fas fa-utensils logo-utensil"></i>
      </div>
      <div>
        <h1 class="text-3xl font-bold text-gray-800">Remiedy</h1>
        <p class="text-sm text-gray-600">Event Catering Services</p>
      </div>
    </div>
    <nav class="flex flex-wrap">
      <a href="index.php?page=home" class="nav-item px-4 py-2 text-gray-700 hover:text-gray-900">Home</a>
      <a href="index.php?page=menu" class="nav-item px-4 py-2 text-gray-700 hover:text-gray-900">Menu</a>
      <a href="index.php?page=cart" class="nav-item px-4 py-2 text-gray-700 hover:text-gray-900">Order (<?php echo array_sum(array_column($_SESSION['cart'], 'quantity')); ?>)</a>
      <a href="index.php?page=contact" class="nav-item px-4 py-2 text-gray-700 hover:text-gray-900">Contact</a>
      <?php if(isset($_SESSION['admin_logged_in'])): ?>
          <a href="index.php?page=admin_dashboard" class="nav-item px-4 py-2 text-gray-700 hover:text-gray-900">Admin</a>
          <a href="index.php?page=admin_logout" class="nav-item px-4 py-2 text-gray-700 hover:text-gray-900">Logout</a>
      <?php else: ?>
          <a href="index.php?page=admin_login" class="nav-item px-4 py-2 text-gray-700 hover:text-gray-900">Admin Login</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<!-- MAIN CONTENT -->
<main class="container mx-auto px-4 py-8">
<?php
// Route based on the $page variable
switch ($page) {

  // ------------------
  // Home Section
  // ------------------
  case 'home':
  default:
    ?>
    <section id="home">
      <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-10">
        <div class="px-6 py-8">
          <h2 class="text-4xl font-bold text-gray-800 mb-4">Welcome to Remiedy</h2>
          <p class="text-xl text-gray-600 italic mb-6">"Catching Hearts and Tantalizing Tastebuds"</p>
          <p class="text-gray-700 mb-6">
            Remiedy Event Catering Services provides exceptional catering solutions for all your events.
            From corporate gatherings to weddings and special celebrations, we deliver delicious cuisine with impeccable service.
          </p>
          <a href="index.php?page=menu" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-3 px-6 rounded-full transition duration-300">Explore Our Menu</a>
        </div>
      </div>
    </section>
    <?php
    break;

  // ------------------
  // Menu Section
  // ------------------
  case 'menu':
    // Fetch food items from the database
    $stmt = $pdo->query("SELECT f.*, c.name AS category_name FROM food_items f JOIN categories c ON f.category_id = c.id WHERE f.is_available = 1");
    $foods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <section id="menu">
      <h2 class="text-3xl font-bold text-gray-800 mb-6">Our Menu</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php foreach($foods as $food): ?>
          <div class="bg-white rounded-lg shadow-md overflow-hidden food-item">
            <div class="h-48 overflow-hidden">
              <img src="food_uploads/<?php echo $food['image']; ?>" alt="<?php echo $food['name']; ?>" class="w-full h-full object-cover">
            </div>
            <div class="p-6">
              <h3 class="text-xl font-bold text-gray-800"><?php echo $food['name']; ?></h3>
              <p class="text-gray-600 mb-4"><?php echo $food['description']; ?></p>
              <p class="text-yellow-600 font-bold mb-4"><?php echo formatCurrency($food['price']); ?></p>
              <a href="index.php?page=food_details&id=<?php echo $food['id']; ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded-md">View Details</a>
              <a href="index.php?action=add_to_cart&id=<?php echo $food['id']; ?>" class="ml-2 bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-md">Add to Cart</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php
    break;

  // ------------------
  // Food Details Section
  // ------------------
  case 'food_details':
    if (!isset($_GET['id'])) { redirect("index.php?page=menu"); }
    $id = (int) $_GET['id'];
    $stmt = $pdo->prepare("SELECT f.*, c.name AS category_name FROM food_items f JOIN categories c ON f.category_id = c.id WHERE f.id = ?");
    $stmt->execute([$id]);
    $food = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$food) { echo "Food item not found."; break; }
    ?>
    <section id="food_details">
      <div class="flex flex-col md:flex-row">
        <div class="w-full md:w-1/2">
          <img src="food_uploads/<?php echo $food['image']; ?>" alt="<?php echo $food['name']; ?>" class="w-full rounded-lg">
        </div>
        <div class="w-full md:w-1/2 md:pl-8">
          <h2 class="text-3xl font-bold text-gray-800 mb-4"><?php echo $food['name']; ?></h2>
          <p class="text-gray-600 mb-4"><?php echo $food['description']; ?></p>
          <p class="text-yellow-600 font-bold mb-4"><?php echo formatCurrency($food['price']); ?></p>
          <a href="index.php?action=add_to_cart&id=<?php echo $food['id']; ?>" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-md">Add to Cart</a>
        </div>
      </div>
    </section>
    <?php
    break;

  // ------------------
  // Cart Section
  // ------------------
  case 'cart':
    ?>
    <section id="cart">
      <h2 class="text-3xl font-bold text-gray-800 mb-6">Your Cart</h2>
      <?php if(empty($_SESSION['cart'])): ?>
        <p>Your cart is empty. <a href="index.php?page=menu" class="text-blue-500">Browse our menu</a>.</p>
      <?php else: ?>
        <table class="min-w-full bg-white border">
          <thead class="bg-gray-100">
            <tr>
              <th class="py-2 px-4 border">Item</th>
              <th class="py-2 px-4 border">Price</th>
              <th class="py-2 px-4 border">Quantity</th>
              <th class="py-2 px-4 border">Total</th>
              <th class="py-2 px-4 border">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($_SESSION['cart'] as $item): ?>
              <tr>
                <td class="py-2 px-4 border"><?php echo $item['name']; ?></td>
                <td class="py-2 px-4 border"><?php echo formatCurrency($item['price']); ?></td>
                <td class="py-2 px-4 border"><?php echo $item['quantity']; ?></td>
                <td class="py-2 px-4 border"><?php echo formatCurrency($item['price'] * $item['quantity']); ?></td>
                <td class="py-2 px-4 border"><a href="index.php?action=remove_from_cart&id=<?php echo $item['id']; ?>" class="text-red-500">Remove</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p class="mt-4 font-bold">Total: <?php 
          $total = 0;
          foreach($_SESSION['cart'] as $item){ $total += $item['price'] * $item['quantity']; }
          echo formatCurrency($total);
        ?></p>
        <a href="index.php?page=order" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded-md mt-4 inline-block">Proceed to Checkout</a>
      <?php endif; ?>
    </section>
    <?php
    break;

  // ------------------
  // Order Form Section
  // ------------------
  case 'order':
    if (empty($_SESSION['cart'])) { redirect("index.php?page=cart"); }
    ?>
    <section id="order">
      <h2 class="text-3xl font-bold text-gray-800 mb-6">Checkout</h2>
      <form action="index.php?page=process_order" method="POST" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label for="customerName" class="block text-gray-700 mb-2">Full Name</label>
            <input type="text" name="customerName" id="customerName" required class="w-full border border-gray-300 p-2 rounded">
          </div>
          <div>
            <label for="customerPhone" class="block text-gray-700 mb-2">Phone Number</label>
            <input type="tel" name="customerPhone" id="customerPhone" required class="w-full border border-gray-300 p-2 rounded">
          </div>
        </div>
        <div>
          <label for="customerEmail" class="block text-gray-700 mb-2">Email Address</label>
          <input type="email" name="customerEmail" id="customerEmail" required class="w-full border border-gray-300 p-2 rounded">
        </div>
        <div>
          <label for="eventDate" class="block text-gray-700 mb-2">Event Date</label>
          <input type="date" name="eventDate" id="eventDate" required class="w-full border border-gray-300 p-2 rounded">
        </div>
        <div>
          <label for="eventLocation" class="block text-gray-700 mb-2">Event Location</label>
          <input type="text" name="eventLocation" id="eventLocation" required class="w-full border border-gray-300 p-2 rounded">
        </div>
        <div>
          <label for="guestCount" class="block text-gray-700 mb-2">Number of Guests</label>
          <input type="number" name="guestCount" id="guestCount" required class="w-full border border-gray-300 p-2 rounded">
        </div>
        <div>
          <label for="specialRequests" class="block text-gray-700 mb-2">Special Requests</label>
          <textarea name="specialRequests" id="specialRequests" rows="4" class="w-full border border-gray-300 p-2 rounded"></textarea>
        </div>
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md">Submit Order</button>
      </form>
    </section>
    <?php
    break;

  // ------------------
  // Order Confirmation Section
  // ------------------
  case 'order_confirmation':
    $order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
    ?>
    <section id="order_confirmation">
      <h2 class="text-3xl font-bold text-gray-800 mb-6">Order Confirmation</h2>
      <p class="text-gray-700">Thank you for your order! Your order number is <strong><?php echo $order_id; ?></strong>. We will contact you soon to confirm the details.</p>
      <a href="index.php?page=home" class="mt-4 inline-block bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded-md">Return Home</a>
    </section>
    <?php
    break;

  // ------------------
  // Contact Section
  // ------------------
  case 'contact':
    ?>
    <section id="contact">
      <h2 class="text-3xl font-bold text-gray-800 mb-6">Contact Us</h2>
      <p class="text-gray-700 mb-6">Have questions? We're here to help with all your catering needs.</p>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div>
          <h3 class="text-xl font-bold text-gray-800 mb-4">Get In Touch</h3>
          <form action="#" method="POST" class="space-y-6">
            <div>
              <label for="name" class="block text-gray-700 mb-2">Your Name</label>
              <input type="text" name="name" id="name" required class="w-full border border-gray-300 p-2 rounded">
            </div>
            <div>
              <label for="email" class="block text-gray-700 mb-2">Your Email</label>
              <input type="email" name="email" id="email" required class="w-full border border-gray-300 p-2 rounded">
            </div>
            <div>
              <label for="message" class="block text-gray-700 mb-2">Your Message</label>
              <textarea name="message" id="message" rows="4" required class="w-full border border-gray-300 p-2 rounded"></textarea>
            </div>
            <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded-md">Send Message</button>
          </form>
        </div>
        <div>
          <h3 class="text-xl font-bold text-gray-800 mb-4">Contact Information</h3>
          <div class="space-y-4">
            <div class="flex items-start">
              <i class="fas fa-map-marker-alt text-yellow-600 mr-3"></i>
              <p>Purok 4 Cogon San Jose, Tacloban City</p>
            </div>
            <div class="flex items-start">
              <i class="fas fa-phone-alt text-yellow-600 mr-3"></i>
              <p>+63 9461434687</p>
            </div>
            <div class="flex items-start">
              <i class="fab fa-facebook-f text-yellow-600 mr-3"></i>
              <p>Remiedy Event Catering Services</p>
            </div>
            <div class="flex items-start">
              <i class="fas fa-clock text-yellow-600 mr-3"></i>
              <p>Business Hours: Mon - Sun: 8:00 AM - 8:00 PM</p>
            </div>
          </div>
        </div>
      </div>
    </section>
    <?php
    break;

  // ------------------
  // Admin Login Section
  // ------------------
  case 'admin_login':
    ?>
    <section id="admin_login">
      <h2 class="text-3xl font-bold text-gray-800 mb-6">Admin Login</h2>
      <?php if(isset($admin_error)): ?>
        <p class="text-red-500"><?php echo $admin_error; ?></p>
      <?php endif; ?>
      <form action="index.php?page=admin_login" method="POST" class="space-y-6 max-w-md mx-auto">
        <div>
          <label for="adminUsername" class="block text-gray-700 mb-2">Username</label>
          <input type="text" name="adminUsername" id="adminUsername" required class="w-full border border-gray-300 p-2 rounded">
        </div>
        <div>
          <label for="adminPassword" class="block text-gray-700 mb-2">Password</label>
          <input type="password" name="adminPassword" id="adminPassword" required class="w-full border border-gray-300 p-2 rounded">
        </div>
        <button type="submit" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded-md">Login</button>
      </form>
    </section>
    <?php
    break;

  // ------------------
  // Admin Dashboard Section
  // ------------------
  case 'admin_dashboard':
    if (!isset($_SESSION['admin_logged_in'])) { redirect("index.php?page=admin_login"); }
    ?>
    <section id="admin_dashboard">
      <h2 class="text-3xl font-bold text-gray-800 mb-6">Admin Dashboard</h2>
      <p>Welcome to the admin panel. Use the navigation below to manage food items and orders.</p>
      <div class="mt-6">
        <a href="index.php?page=food_management" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md">Manage Food Items</a>
        <a href="index.php?page=manage_orders" class="ml-4 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md">Manage Orders</a>
      </div>
    </section>
    <?php
    break;

  // ------------------
  // Admin - Manage Food Items
  // ------------------
  case 'food_management':
    if (!isset($_SESSION['admin_logged_in'])) { redirect("index.php?page=admin_login"); }
    // Fetch all food items
    $stmt = $pdo->query("SELECT f.*, c.name AS category_name FROM food_items f JOIN categories c ON f.category_id = c.id");
    ?>
    <section id="food_management">
      <h2 class="text-3xl font-bold text-gray-800 mb-6">Manage Food Items</h2>
      <a href="index.php?page=add_food" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">Add New Food</a>
      <table class="min-w-full mt-6 border">
        <thead class="bg-gray-100">
          <tr>
            <th class="py-2 px-4 border">Name</th>
            <th class="py-2 px-4 border">Category</th>
            <th class="py-2 px-4 border">Price</th>
            <th class="py-2 px-4 border">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while($food = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
            <tr>
              <td class="py-2 px-4 border"><?php echo $food['name']; ?></td>
              <td class="py-2 px-4 border"><?php echo $food['category_name']; ?></td>
              <td class="py-2 px-4 border"><?php echo formatCurrency($food['price']); ?></td>
              <td class="py-2 px-4 border">
                <a href="index.php?page=edit_food&id=<?php echo $food['id']; ?>" class="text-blue-500">Edit</a> |
                <a href="index.php?page=delete_food&id=<?php echo $food['id']; ?>" class="text-red-500" onclick="return confirm('Are you sure?');">Delete</a>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </section>
    <?php
    break;

  // ------------------
  // Admin - Add Food Item
  // ------------------
  case 'add_food':
    if (!isset($_SESSION['admin_logged_in'])) { redirect("index.php?page=admin_login"); }
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
      $name = clean_input($_POST['name']);
      $category_id = (int) $_POST['category_id'];
      $description = clean_input($_POST['description']);
      $price = (float) $_POST['price'];
      // For simplicity, we assume image upload is optional and use default if none provided
      $image = 'default_food.jpg';
      if (isset($_FILES['image']) && $_FILES['image']['name'] != '') {
         // Basic file upload (improve validations as needed)
         $target_dir = "food_uploads/";
         if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
         $fileName = basename($_FILES["image"]["name"]);
         $target_file = $target_dir . uniqid() . '_' . time() . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
         if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
             $image = basename($target_file);
         }
      }
      $stmt = $pdo->prepare("INSERT INTO food_items (category_id, name, description, price, image) VALUES (?,?,?,?,?)");
      $stmt->execute([$category_id, $name, $description, $price, $image]);
      redirect("index.php?page=food_management");
    }
    // Fetch categories for dropdown
    $cats = $pdo->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <section id="add_food">
      <h2 class="text-3xl font-bold text-gray-800 mb-6">Add New Food Item</h2>
      <form action="index.php?page=add_food" method="POST" enctype="multipart/form-data" class="space-y-4 max-w-md">
        <div>
          <label for="name" class="block">Food Name:</label>
          <input type="text" name="name" id="name" required class="border p-2 w-full">
        </div>
        <div>
          <label for="category_id" class="block">Category:</label>
          <select name="category_id" id="category_id" required class="border p-2 w-full">
            <?php foreach($cats as $cat): ?>
              <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="description" class="block">Description:</label>
          <textarea name="description" id="description" rows="3" required class="border p-2 w-full"></textarea>
        </div>
        <div>
          <label for="price" class="block">Price:</label>
          <input type="number" name="price" id="price" step="0.01" required class="border p-2 w-full">
        </div>
        <div>
          <label for="image" class="block">Food Image:</label>
          <input type="file" name="image" id="image" class="border p-2 w-full">
        </div>
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">Add Food</button>
      </form>
    </section>
    <?php
    break;

  // ------------------
  // Admin - Edit Food Item
  // ------------------
  case 'edit_food':
    if (!isset($_SESSION['admin_logged_in'])) { redirect("index.php?page=admin_login"); }
    if (!isset($_GET['id'])) { redirect("index.php?page=food_management"); }
    $id = (int) $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM food_items WHERE id = ?");
    $stmt->execute([$id]);
    $food = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$food) { echo "Food item not found."; break; }
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
      $name = clean_input($_POST['name']);
      $category_id = (int) $_POST['category_id'];
      $description = clean_input($_POST['description']);
      $price = (float) $_POST['price'];
      $image = $food['image'];
      if (isset($_FILES['image']) && $_FILES['image']['name'] != '') {
         $target_dir = "food_uploads/";
         if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
         $fileName = basename($_FILES["image"]["name"]);
         $target_file = $target_dir . uniqid() . '_' . time() . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
         if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
             $image = basename($target_file);
         }
      }
      $stmt = $pdo->prepare("UPDATE food_items SET category_id = ?, name = ?, description = ?, price = ?, image = ? WHERE id = ?");
      $stmt->execute([$category_id, $name, $description, $price, $image, $id]);
      redirect("index.php?page=food_management");
    }
    $cats = $pdo->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <section id="edit_food">
      <h2 class="text-3xl font-bold text-gray-800 mb-6">Edit Food Item</h2>
      <form action="index.php?page=edit_food&id=<?php echo $id; ?>" method="POST" enctype="multipart/form-data" class="space-y-4 max-w-md">
        <div>
          <label for="name" class="block">Food Name:</label>
          <input type="text" name="name" id="name" value="<?php echo $food['name']; ?>" required class="border p-2 w-full">
        </div>
        <div>
          <label for="category_id" class="block">Category:</label>
          <select name="category_id" id="category_id" required class="border p-2 w-full">
            <?php foreach($cats as $cat): ?>
              <option value="<?php echo $cat['id']; ?>" <?php if($food['category_id'] == $cat['id']) echo 'selected'; ?>><?php echo $cat['name']; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="description" class="block">Description:</label>
          <textarea name="description" id="description" rows="3" required class="border p-2 w-full"><?php echo $food['description']; ?></textarea>
        </div>
        <div>
          <label for="price" class="block">Price:</label>
          <input type="number" name="price" id="price" step="0.01" value="<?php echo $food['price']; ?>" required class="border p-2 w-full">
        </div>
        <div>
          <label for="image" class="block">Food Image (leave blank to keep current):</label>
          <input type="file" name="image" id="image" class="border p-2 w-full">
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Update Food</button>
      </form>
    </section>
    <?php
    break;

  // ------------------
  // Admin - Delete Food Item
  // ------------------
  case 'delete_food':
    if (!isset($_SESSION['admin_logged_in'])) { redirect("index.php?page=admin_login"); }
    if (isset($_GET['id'])) {
      $id = (int) $_GET['id'];
      $stmt = $pdo->prepare("DELETE FROM food_items WHERE id = ?");
      $stmt->execute([$id]);
    }
    redirect("index.php?page=food_management");
    break;

  // ------------------
  // Admin - Manage Orders
  // ------------------
  case 'manage_orders':
    if (!isset($_SESSION['admin_logged_in'])) { redirect("index.php?page=admin_login"); }
    $stmt = $pdo->query("SELECT o.*, u.name AS customer_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC");
    ?>
    <section id="manage_orders">
      <h2 class="text-3xl font-bold text-gray-800 mb-6">Manage Orders</h2>
      <table class="min-w-full border">
        <thead class="bg-gray-100">
          <tr>
            <th class="py-2 px-4 border">Order ID</th>
            <th class="py-2 px-4 border">Customer</th>
            <th class="py-2 px-4 border">Event Date</th>
            <th class="py-2 px-4 border">Total</th>
            <th class="py-2 px-4 border">Status</th>
            <th class="py-2 px-4 border">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while($order = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
            <tr>
              <td class="py-2 px-4 border"><?php echo $order['id']; ?></td>
              <td class="py-2 px-4 border"><?php echo $order['customer_name']; ?></td>
              <td class="py-2 px-4 border"><?php echo $order['event_date']; ?></td>
              <td class="py-2 px-4 border"><?php echo formatCurrency($order['total_amount']); ?></td>
              <td class="py-2 px-4 border"><?php echo ucfirst($order['status']); ?></td>
              <td class="py-2 px-4 border"><a href="index.php?page=order_confirmation&order_id=<?php echo $order['id']; ?>" class="text-blue-500">View</a></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </section>
    <?php
    break;
}
?>
</main>

<!-- FOOTER -->
<footer class="bg-gray-800 text-white py-10">
  <div class="container mx-auto px-4">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
      <div>
        <div class="flex items-center mb-4">
          <div class="logo-circle mr-3 bg-white">
            <i class="fas fa-utensils logo-utensil text-gray-800"></i>
          </div>
          <div>
            <h3 class="text-2xl font-bold">Remiedy</h3>
            <p class="text-sm text-gray-300">Event Catering Services</p>
          </div>
        </div>
        <p class="text-gray-300 mb-4">"Catching Hearts and Tantalizing Tastebuds"</p>
        <div class="flex space-x-4">
          <a href="#" class="text-white hover:text-yellow-400"><i class="fab fa-facebook-f"></i></a>
          <a href="#" class="text-white hover:text-yellow-400"><i class="fab fa-instagram"></i></a>
          <a href="#" class="text-white hover:text-yellow-400"><i class="fab fa-twitter"></i></a>
        </div>
      </div>
      <div>
        <h3 class="text-lg font-bold mb-4">Contact Information</h3>
        <div class="space-y-3">
          <div class="flex items-start">
            <i class="fas fa-map-marker-alt mt-1 mr-3 text-yellow-400"></i>
            <p>Purok 4 Cogon San Jose, Tacloban City</p>
          </div>
          <div class="flex items-start">
            <i class="fas fa-phone-alt mt-1 mr-3 text-yellow-400"></i>
            <p>+63 9461434687</p>
          </div>
          <div class="flex items-start">
            <i class="fab fa-facebook-f mt-1 mr-3 text-yellow-400"></i>
            <p>Remiedy Event Catering Services</p>
          </div>
        </div>
      </div>
      <div>
        <h3 class="text-lg font-bold mb-4">Quick Links</h3>
        <ul class="space-y-2">
          <li><a href="index.php?page=home" class="text-gray-300 hover:text-white">Home</a></li>
          <li><a href="index.php?page=menu" class="text-gray-300 hover:text-white">Menu</a></li>
          <li><a href="index.php?page=cart" class="text-gray-300 hover:text-white">Order</a></li>
          <li><a href="index.php?page=contact" class="text-gray-300 hover:text-white">Contact</a></li>
        </ul>
      </div>
    </div>
    <div class="border-t border-gray-700 mt-8 pt-6 text-center text-gray-400">
      <p>&copy; 2025 Remiedy Event Catering Services. All rights reserved.</p>
    </div>
  </div>
</footer>
</body>
</html>
