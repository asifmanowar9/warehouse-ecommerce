# Warehouse E-commerce System

A dual-purpose web application that combines warehouse management with an integrated e-commerce storefront.

## Features

### Admin/Staff Portal

- **Dashboard**: Real-time inventory insights and scheduled activities
- **Inventory Management**: Product tracking with stock alerts
- **Supplier Management**: Vendor relationships and purchase orders
- **Staff Controls**: User role management (admin only)
- **Reports**: Sales and inventory analytics

### Customer Portal

- **Product Browsing**: Search, filter, and paginate through available products
- **Shopping Cart**: Add products and manage purchases
- **Order Management**: View and track order status
- **User Profile**: Account settings and order history

## Tech Stack

- PHP 7.4+
- MySQL 5.7+
- Bootstrap 5
- Chart.js (for analytics)

## Installation

1. Clone the repository to your web server
2. Import `warehouse_db.sql` to your MySQL server
3. Configure database connection in `db.php`
4. Access the application through your web browser

## User Roles

- **Admin**: Complete access to all features
- **Staff**: Inventory and supplier management without user administration
- **Customer**: E-commerce store access only

## Default Login

- Username: admin
- Password: admin123
- **Important**: Change default password after first login

## Database Structure

The application uses a relational database with the following key tables:

- `users`: Authentication and role management
- `products`: Inventory items with pricing and supplier info
- `stock_movements`: Track inventory changes
- `orders`: Customer purchases and fulfillment tracking
- `suppliers`: Vendor information for restocking

## Screenshots

_[Screenshots would be added here]_

## License

This project is proprietary software. Unauthorized use, modification, or distribution is prohibited.

---

© 2024 Warehouse E-commerce System by @Asif_Manowar
