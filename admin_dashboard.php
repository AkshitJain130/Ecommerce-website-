<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'login_register');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch products
$sql = "SELECT * FROM products";
$result = $conn->query($sql);

// Check for form submission for adding a product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['product_image'])) {
    $productName = $_POST['product_name'];
    $productPrice = $_POST['product_price'];
    $productImage = $_FILES['product_image'];

    // Handle image upload
    $targetDir = 'uploads/';

    // Ensure uploads directory exists
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true); // Create the directory if it doesn't exist
    }

    // Sanitize file name and add timestamp for uniqueness
    $imageName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($productImage['name']));
    $targetFile = $targetDir . $imageName;

    // Check if the file was uploaded successfully
    if (move_uploaded_file($productImage['tmp_name'], $targetFile)) {
        // Insert new product into the database
        $stmt = $conn->prepare("INSERT INTO products (name, price, image) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $productName, $productPrice, $imageName);
        $stmt->execute();
        $stmt->close();
        
        // Return the new product information for AJAX update
        echo json_encode([
            'id' => $conn->insert_id,
            'name' => $productName,
            'price' => $productPrice,
            'image' => $imageName
        ]);
        exit;
    } else {
        echo json_encode(['error' => 'Failed to upload image.']);
        exit;
    }
}

// Check for form submission for deleting a product
if (isset($_POST['delete_product'])) {
    $productId = $_POST['product_id'];
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Basic Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: #f7f8fa;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            color: #333;
        }

        /* Topbar Styling */
        .topbar {
            position: fixed;
            top: 0;
            width: 100%;
            height: 60px;
            background-color: #2c3e50; /* Darker blue-gray */
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 1000;
        }

        .topbar h1 {
            font-size: 22px;
            margin-left: 10px;
        }

        .toggle-btn {
            background: none;
            border: none;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            width: 40px;
            height: 30px;
        }

        .bar {
            display: block;
            width: 100%;
            height: 4px;
            background-color: white;
            border-radius: 2px;
            transition: 0.3s;
        }

        /* Sidebar Styling */
        .sidebar {
            width: 240px;
            height: 100vh;
            background-color: #34495e; /* Dark blue-gray */
            padding-top: 80px;
            position: fixed;
            left: -240px;
            transition: left 0.3s ease;
            box-shadow: 4px 0px 10px rgba(0, 0, 0, 0.2);
        }

        .sidebar.open {
            left: 0;
        }

        .sidebar a {
            display: block;
            color: white;
            padding: 15px;
            text-decoration: none;
            font-size: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: background-color 0.3s ease;
        }

        .sidebar a:hover {
            background-color: #16a085; /* Teal for hover effect */
        }

        /* Main Content Styling */
        .main {
            margin-left: 0;
            padding: 80px 20px 20px 20px;
            width: 100%;
            transition: margin-left 0.3s ease;
            flex-grow: 1;
        }

        .main h2 {
            margin-bottom: 20px;
            font-size: 24px;
            color: #2c3e50;
        }

        /* Product Form */
        .product-form input, .product-form button {
            padding: 12px;
            margin-top: 10px;
            width: 100%;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        .product-form input[type="file"] {
            padding: 8px;
        }

        .product-form button {
            background-color: #2ecc71; /* Green button */
            color: white;
            font-size: 16px;
            cursor: pointer;
            border: none;
        }

        .product-form button:hover {
            background-color: #27ae60; /* Darker green on hover */
        }

        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .product-item {
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .product-item:hover {
            transform: translateY(-10px);
        }

        .product-item img {
            width: 100%;
            height: auto;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        .product-item h6 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .product-item p {
            color: #7f8c8d; /* Lighter gray */
            font-size: 16px;
            margin-bottom: 15px;
        }

        .product-item form button {
            padding: 8px 15px;
            background-color: #e74c3c; /* Red for delete button */
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .product-item form button:hover {
            background-color: #c0392b; /* Darker red on hover */
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .topbar h1 {
                font-size: 18px;
            }

            .sidebar {
                width: 200px;
            }

            .main {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

    <!-- Topbar -->
    <div class="topbar">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <span class="bar"></span>
            <span class="bar"></span>
            <span class="bar"></span>
        </button>
        <h1>Admin Dashboard</h1>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <a href="#">Dashboard</a>
        <a href="#">Manage Products</a>
        <a href="#">Orders</a>
        <a href="#">Settings</a>
        <a href="index.php">Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main">
        <h2>Add New Product</h2>
        <form class="product-form" id="productForm" enctype="multipart/form-data">
            <label for="product_name">Product Name:</label>
            <input type="text" name="product_name" id="product_name" required>

            <label for="product_price">Product Price:</label>
            <input type="number" step="0.01" name="product_price" id="product_price" required>

            <label for="product_image">Product Image:</label>
            <input type="file" name="product_image" id="product_image" required>

            <button type="submit">Add Product</button>
        </form>

        <h2>Product List</h2>
        <div class="product-grid" id="productGrid">
            <?php while ($row = $result->fetch_assoc()) { ?>
                <div class="product-item" id="product-<?php echo $row['id']; ?>">
                    <img src="uploads/<?php echo $row['image']; ?>" alt="<?php echo $row['name']; ?>">
                    <h6><?php echo $row['name']; ?></h6>
                    <p>$<?php echo $row['price']; ?></p>
                    <form action="admin_dashboard.php" method="post">
                        <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" name="delete_product">Delete</button>
                    </form>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- JavaScript to Toggle Sidebar and Handle Form Submission -->
    <script>
        // Toggle Sidebar
        function toggleSidebar() {
            var sidebar = document.getElementById("sidebar");
            sidebar.classList.toggle("open");

            var main = document.querySelector(".main");
            if (sidebar.classList.contains("open")) {
                main.style.marginLeft = "240px";
            } else {
                main.style.marginLeft = "0";
            }
        }

        // Handle Add Product Form Submission
        document.getElementById('productForm').addEventListener('submit', function(event) {
            event.preventDefault(); // Prevent form submission

            var formData = new FormData(this);
            
            // Make AJAX request
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'admin_dashboard.php', true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.id) {
                        // Dynamically add the new product to the grid
                        var newProduct = document.createElement('div');
                        newProduct.classList.add('product-item');
                        newProduct.id = 'product-' + response.id;
                        newProduct.innerHTML = `
                            <img src="uploads/${response.image}" alt="${response.name}">
                            <h6>${response.name}</h6>
                            <p>$${response.price}</p>
                            <form action="admin_dashboard.php" method="post">
                                <input type="hidden" name="product_id" value="${response.id}">
                                <button type="submit" name="delete_product">Delete</button>
                            </form>
                        `;
                        document.getElementById('productGrid').appendChild(newProduct);
                        // Clear form inputs
                        document.getElementById('productForm').reset();
                    } else {
                        alert('Error adding product: ' + response.error);
                    }
                } else {
                    alert('An error occurred while adding the product.');
                }
            };
            xhr.send(formData);
        });
    </script>

</body>
</html>

<?php $conn->close(); ?>
