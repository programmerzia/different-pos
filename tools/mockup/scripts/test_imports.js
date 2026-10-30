const { CATEGORIES, PRODUCTS, CUSTOMERS, VENDORS } = require('./data.js');
const { SALES, PURCHASES, ACCOUNTS, EXPENSES, FIXED_ASSETS, USERS, AUDIT_LOGS } = require('./more_data.js');

console.log("Categories:", CATEGORIES.length);
console.log("Products:", PRODUCTS.length);
console.log("Customers:", CUSTOMERS.length);
console.log("Vendors:", VENDORS.length);
console.log("Sales:", SALES.length);
console.log("Purchases:", PURCHASES.length);
console.log("Accounts:", ACCOUNTS.length);
console.log("Expenses:", EXPENSES.length);
console.log("Fixed Assets:", FIXED_ASSETS.length);
console.log("Users:", USERS.length);
console.log("Audit Logs:", AUDIT_LOGS.length);
