const fs = require('fs');
const { CATEGORIES, PRODUCTS, CUSTOMERS, VENDORS } = require('./data.js');
const { SALES, PURCHASES, ACCOUNTS, EXPENSES, FIXED_ASSETS, USERS, AUDIT_LOGS } = require('./more_data.js');

const allData = {
  CATEGORIES,
  PRODUCTS,
  CUSTOMERS,
  VENDORS,
  SALES,
  PURCHASES,
  ACCOUNTS,
  EXPENSES,
  FIXED_ASSETS,
  USERS,
  AUDIT_LOGS
};

fs.writeFileSync('scripts/mock_data.json', JSON.stringify(allData, null, 2));
console.log("Exported mock_data.json successfully, bytes:", fs.statSync('scripts/mock_data.json').size);
