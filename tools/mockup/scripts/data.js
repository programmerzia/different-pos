// Comprehensive mock data for DIFFERENT FASHION Retail POS & Back-office

const CATEGORIES = [
  { id: 'all', name_en: 'All Items', name_bn: 'সকল আইটেম' },
  { id: 'panjabi', name_en: 'Panjabi', name_bn: 'পাঞ্জাবি' },
  { id: 'saree', name_en: 'Saree', name_bn: 'শাড়ি' },
  { id: 'three-piece', name_en: 'Three-piece', name_bn: 'থ্রি-পিস' },
  { id: 't-shirt', name_en: 'T-shirt', name_bn: 'টি-শার্ট' },
  { id: 'polo', name_en: 'Polo', name_bn: 'পোলো শার্ট' },
  { id: 'jeans', name_en: 'Jeans', name_bn: 'জিন্স' },
  { id: 'shirt', name_en: 'Shirt', name_bn: 'শার্ট' },
  { id: 'kurti', name_en: 'Kurti', name_bn: 'কুর্তি' },
  { id: 'hijab', name_en: 'Hijab', name_bn: 'হিজাব' },
  { id: 'shoes', name_en: 'Shoes', name_bn: 'জুতা' },
  { id: 'belts', name_en: 'Belts', name_bn: 'বেল্ট' },
  { id: 'kids', name_en: 'Kids wear', name_bn: 'বাচ্চাদের পোশাক' },
];

// 42 realistic clothing products with sizes, colors, variants, prices, costs, stock per branch
const PRODUCTS = [
  // 1-5: Panjabi
  {
    id: 'p1', sku: 'PAN-COT-M', barcode: '8941001', name_en: 'Cotton Panjabi Classic', name_bn: 'সুতি পাঞ্জাবি ক্লাসিক',
    category: 'panjabi', brand: 'DF Signature', vendor_id: 'v1', price: 1450, cost: 950, stock_main: 3, stock_b2: 12,
    reorder_level: 5, initials: 'CP', color: '#0F766E',
    variants: [
      { size: 'S', color: 'White', sku: 'PAN-COT-S-WH', barcode: '8941001-1', stock_main: 1, stock_b2: 4, price: 1450 },
      { size: 'M', color: 'White', sku: 'PAN-COT-M-WH', barcode: '8941001-2', stock_main: 1, stock_b2: 4, price: 1450 },
      { size: 'L', color: 'Navy', sku: 'PAN-COT-L-NV', barcode: '8941001-3', stock_main: 1, stock_b2: 4, price: 1450 },
    ]
  },
  {
    id: 'p2', sku: 'PAN-KAB-L', barcode: '8941002', name_en: 'Premium Kabli Panjabi L', name_bn: 'প্রিমিয়াম কাবলি পাঞ্জাবি - এল',
    category: 'panjabi', brand: 'DF Elite', vendor_id: 'v1', price: 2850, cost: 1800, stock_main: 18, stock_b2: 8,
    reorder_level: 5, initials: 'KP', color: '#D97706',
    variants: [
      { size: 'M', color: 'Black', sku: 'PAN-KAB-M-BK', barcode: '8941002-1', stock_main: 6, stock_b2: 2, price: 2850 },
      { size: 'L', color: 'Black', sku: 'PAN-KAB-L-BK', barcode: '8941002-2', stock_main: 6, stock_b2: 3, price: 2850 },
      { size: 'XL', color: 'Olive', sku: 'PAN-KAB-XL-OL', barcode: '8941002-3', stock_main: 6, stock_b2: 3, price: 2850 },
    ]
  },
  {
    id: 'p3', sku: 'PAN-SEM-XL', barcode: '8941003', name_en: 'Semi-fitted Silk Panjabi', name_bn: 'সেমি-ফিটেড সিল্ক পাঞ্জাবি',
    category: 'panjabi', brand: 'DF Signature', vendor_id: 'v7', price: 3200, cost: 2100, stock_main: 10, stock_b2: 5,
    reorder_level: 4, initials: 'SP', color: '#6D28D9',
    variants: [
      { size: 'M', color: 'Beige', sku: 'PAN-SEM-M-BG', barcode: '8941003-1', stock_main: 3, stock_b2: 2, price: 3200 },
      { size: 'L', color: 'Maroon', sku: 'PAN-SEM-L-MR', barcode: '8941003-2', stock_main: 4, stock_b2: 2, price: 3200 },
      { size: 'XL', color: 'Maroon', sku: 'PAN-SEM-XL-MR', barcode: '8941003-3', stock_main: 3, stock_b2: 1, price: 3200 },
    ]
  },
  {
    id: 'p4', sku: 'PAN-JAC-40', barcode: '8941004', name_en: 'Jacquard Designer Panjabi', name_bn: 'জ্যাককার্ড ডিজাইনার পাঞ্জাবি',
    category: 'panjabi', brand: 'DF Luxe', vendor_id: 'v1', price: 4200, cost: 2750, stock_main: 8, stock_b2: 6,
    reorder_level: 3, initials: 'JP', color: '#0369A1',
    variants: [
      { size: 'M', color: 'Royal Blue', sku: 'PAN-JAC-M-RB', barcode: '8941004-1', stock_main: 4, stock_b2: 3, price: 4200 },
      { size: 'L', color: 'Emerald', sku: 'PAN-JAC-L-EM', barcode: '8941004-2', stock_main: 4, stock_b2: 3, price: 4200 },
    ]
  },
  {
    id: 'p5', sku: 'PAN-EMB-M', barcode: '8941005', name_en: 'Embroidered Collar Panjabi', name_bn: 'এমব্রয়ডারি কলার পাঞ্জাবি',
    category: 'panjabi', brand: 'DF Signature', vendor_id: 'v7', price: 2150, cost: 1350, stock_main: 14, stock_b2: 10,
    reorder_level: 6, initials: 'EP', color: '#BE123C',
    variants: [
      { size: 'M', color: 'White', sku: 'PAN-EMB-M-WH', barcode: '8941005-1', stock_main: 7, stock_b2: 5, price: 2150 },
      { size: 'L', color: 'Mustard', sku: 'PAN-EMB-L-MU', barcode: '8941005-2', stock_main: 7, stock_b2: 5, price: 2150 },
    ]
  },

  // 6-9: Saree
  {
    id: 'p6', sku: 'SAR-JAM-01', barcode: '8941006', name_en: 'Traditional Jamdani Saree', name_bn: 'ঐতিহ্যবাহী জামদানি শাড়ি',
    category: 'saree', brand: 'Tangail Handloom', vendor_id: 'v6', price: 6500, cost: 4200, stock_main: 6, stock_b2: 4,
    reorder_level: 2, initials: 'JS', color: '#BE185D',
    variants: [
      { size: 'Free', color: 'Red & Gold', sku: 'SAR-JAM-RD', barcode: '8941006-1', stock_main: 3, stock_b2: 2, price: 6500 },
      { size: 'Free', color: 'Black & Copper', sku: 'SAR-JAM-BK', barcode: '8941006-2', stock_main: 3, stock_b2: 2, price: 6500 },
    ]
  },
  {
    id: 'p7', sku: 'SAR-KAT-02', barcode: '8941007', name_en: 'Katan Silk Festive Saree', name_bn: 'কাতান সিল্ক উৎসব শাড়ি',
    category: 'saree', brand: 'Mirpur Benarasi', vendor_id: 'v9', price: 8900, cost: 5800, stock_main: 4, stock_b2: 3,
    reorder_level: 2, initials: 'KS', color: '#D97706',
    variants: [
      { size: 'Free', color: 'Magenta', sku: 'SAR-KAT-MG', barcode: '8941007-1', stock_main: 2, stock_b2: 1, price: 8900 },
      { size: 'Free', color: 'Bottle Green', sku: 'SAR-KAT-BG', barcode: '8941007-2', stock_main: 2, stock_b2: 2, price: 8900 },
    ]
  },
  {
    id: 'p8', sku: 'SAR-COT-03', barcode: '8941008', name_en: 'Tangail Pure Cotton Saree', name_bn: 'টাঙ্গাইল সুতি তাঁতের শাড়ি',
    category: 'saree', brand: 'Tangail Handloom', vendor_id: 'v6', price: 1850, cost: 1150, stock_main: 15, stock_b2: 12,
    reorder_level: 5, initials: 'TS', color: '#0F766E',
    variants: [
      { size: 'Free', color: 'Pastel Blue', sku: 'SAR-COT-BL', barcode: '8941008-1', stock_main: 8, stock_b2: 6, price: 1850 },
      { size: 'Free', color: 'Olive Green', sku: 'SAR-COT-OL', barcode: '8941008-2', stock_main: 7, stock_b2: 6, price: 1850 },
    ]
  },
  {
    id: 'p9', sku: 'SAR-GEO-04', barcode: '8941009', name_en: 'Printed Georgette Casual Saree', name_bn: 'প্রিন্টেড জর্জেট ক্যাজুয়াল শাড়ি',
    category: 'saree', brand: 'DF Daily', vendor_id: 'v10', price: 2450, cost: 1550, stock_main: 11, stock_b2: 9,
    reorder_level: 4, initials: 'GS', color: '#7C3AED',
    variants: [
      { size: 'Free', color: 'Floral Grey', sku: 'SAR-GEO-GY', barcode: '8941009-1', stock_main: 6, stock_b2: 5, price: 2450 },
      { size: 'Free', color: 'Navy Print', sku: 'SAR-GEO-NV', barcode: '8941009-2', stock_main: 5, stock_b2: 4, price: 2450 },
    ]
  },

  // 10-13: Three-piece
  {
    id: 'p10', sku: 'TP-GEO-02', barcode: '8941010', name_en: 'Georgette Three-piece Set', name_bn: 'জর্জেট থ্রি-পিস সেট',
    category: 'three-piece', brand: 'DF Festive', vendor_id: 'v9', price: 3450, cost: 2100, stock_main: 8, stock_b2: 5,
    reorder_level: 3, initials: 'TP', color: '#BE185D',
    variants: [
      { size: 'Unstitched', color: 'Peach', sku: 'TP-GEO-PC', barcode: '8941010-1', stock_main: 4, stock_b2: 2, price: 3450 },
      { size: 'Unstitched', color: 'Sky Blue', sku: 'TP-GEO-SB', barcode: '8941010-2', stock_main: 4, stock_b2: 3, price: 3450 },
    ]
  },
  {
    id: 'p11', sku: 'TP-COT-03', barcode: '8941011', name_en: 'Block Print Cotton Three-piece', name_bn: 'ব্লক প্রিন্ট সুতি থ্রি-পিস',
    category: 'three-piece', brand: 'DF Daily', vendor_id: 'v1', price: 2200, cost: 1350, stock_main: 16, stock_b2: 14,
    reorder_level: 5, initials: 'BP', color: '#15803D',
    variants: [
      { size: 'Unstitched', color: 'Yellow', sku: 'TP-COT-YL', barcode: '8941011-1', stock_main: 8, stock_b2: 7, price: 2200 },
      { size: 'Unstitched', color: 'Teal', sku: 'TP-COT-TL', barcode: '8941011-2', stock_main: 8, stock_b2: 7, price: 2200 },
    ]
  },
  {
    id: 'p12', sku: 'TP-LAW-04', barcode: '8941012', name_en: 'Premium Swiss Lawn Three-piece', name_bn: 'প্রিমিয়াম সুইস লন থ্রি-পিস',
    category: 'three-piece', brand: 'DF Luxe', vendor_id: 'v9', price: 4600, cost: 2950, stock_main: 7, stock_b2: 4,
    reorder_level: 3, initials: 'SL', color: '#4338CA',
    variants: [
      { size: 'Unstitched', color: 'Lavender', sku: 'TP-LAW-LV', barcode: '8941012-1', stock_main: 4, stock_b2: 2, price: 4600 },
      { size: 'Unstitched', color: 'Mint', sku: 'TP-LAW-MT', barcode: '8941012-2', stock_main: 3, stock_b2: 2, price: 4600 },
    ]
  },
  {
    id: 'p13', sku: 'TP-EMB-05', barcode: '8941013', name_en: 'Heavy Embroidery Organza Set', name_bn: 'অরগাঞ্জা কারুকাজ থ্রি-পিস',
    category: 'three-piece', brand: 'DF Festive', vendor_id: 'v9', price: 5800, cost: 3700, stock_main: 5, stock_b2: 2,
    reorder_level: 2, initials: 'HE', color: '#D97706',
    variants: [
      { size: 'Unstitched', color: 'Rose Gold', sku: 'TP-EMB-RG', barcode: '8941013-1', stock_main: 3, stock_b2: 1, price: 5800 },
      { size: 'Unstitched', color: 'Ivory', sku: 'TP-EMB-IV', barcode: '8941013-2', stock_main: 2, stock_b2: 1, price: 5800 },
    ]
  },

  // 14-17: T-shirt
  {
    id: 'p14', sku: 'TSH-CRE-M', barcode: '8941014', name_en: 'Crewneck Heavy Cotton Tee', name_bn: 'ক্রু-নেক কটন টি-শার্ট',
    category: 't-shirt', brand: 'DF Basic', vendor_id: 'v4', price: 490, cost: 240, stock_main: 45, stock_b2: 30,
    reorder_level: 10, initials: 'CT', color: '#334155',
    variants: [
      { size: 'M', color: 'Black', sku: 'TSH-CRE-M-BK', barcode: '8941014-1', stock_main: 15, stock_b2: 10, price: 490 },
      { size: 'L', color: 'White', sku: 'TSH-CRE-L-WH', barcode: '8941014-2', stock_main: 15, stock_b2: 10, price: 490 },
      { size: 'XL', color: 'Navy', sku: 'TSH-CRE-XL-NV', barcode: '8941014-3', stock_main: 15, stock_b2: 10, price: 490 },
    ]
  },
  {
    id: 'p15', sku: 'TSH-OVR-L', barcode: '8941015', name_en: 'Oversized Drop-Shoulder Tee', name_bn: 'ওভারসাইজড ড্রপ শোল্ডার টি-শার্ট',
    category: 't-shirt', brand: 'DF Youth', vendor_id: 'v4', price: 650, cost: 320, stock_main: 28, stock_b2: 18,
    reorder_level: 8, initials: 'OT', color: '#0369A1',
    variants: [
      { size: 'M', color: 'Acid Grey', sku: 'TSH-OVR-M-GY', barcode: '8941015-1', stock_main: 14, stock_b2: 9, price: 650 },
      { size: 'L', color: 'Washed Olive', sku: 'TSH-OVR-L-OL', barcode: '8941015-2', stock_main: 14, stock_b2: 9, price: 650 },
    ]
  },
  {
    id: 'p16', sku: 'TSH-HEN-M', barcode: '8941016', name_en: 'Henley Waffle Knit Tee', name_bn: 'হেনলি ওয়াফেল নিট টি-শার্ট',
    category: 't-shirt', brand: 'DF Basic', vendor_id: 'v4', price: 580, cost: 290, stock_main: 19, stock_b2: 12,
    reorder_level: 5, initials: 'HT', color: '#6D28D9',
    variants: [
      { size: 'M', color: 'Maroon', sku: 'TSH-HEN-M-MR', barcode: '8941016-1', stock_main: 10, stock_b2: 6, price: 580 },
      { size: 'L', color: 'Charcoal', sku: 'TSH-HEN-L-CH', barcode: '8941016-2', stock_main: 9, stock_b2: 6, price: 580 },
    ]
  },
  {
    id: 'p17', sku: 'TSH-SLB-L', barcode: '8941017', name_en: 'Slub Cotton Graphic Tee', name_bn: 'স্লাব কটন গ্রাফিক্স টি-শার্ট',
    category: 't-shirt', brand: 'DF Youth', vendor_id: 'v4', price: 550, cost: 270, stock_main: 22, stock_b2: 15,
    reorder_level: 6, initials: 'GT', color: '#0F766E',
    variants: [
      { size: 'M', color: 'Beige', sku: 'TSH-SLB-M-BG', barcode: '8941017-1', stock_main: 11, stock_b2: 7, price: 550 },
      { size: 'L', color: 'Black', sku: 'TSH-SLB-L-BK', barcode: '8941017-2', stock_main: 11, stock_b2: 8, price: 550 },
    ]
  },

  // 18-20: Polo
  {
    id: 'p18', sku: 'POL-PIQ-L', barcode: '8941018', name_en: 'Pique Cotton Polo L', name_bn: 'পিক সুতি পোলো শার্ট - এল',
    category: 'polo', brand: 'DF Classic', vendor_id: 'v4', price: 890, cost: 480, stock_main: 32, stock_b2: 20,
    reorder_level: 8, initials: 'PP', color: '#7C3AED',
    variants: [
      { size: 'M', color: 'Navy', sku: 'POL-PIQ-M-NV', barcode: '8941018-1', stock_main: 11, stock_b2: 7, price: 890 },
      { size: 'L', color: 'White', sku: 'POL-PIQ-L-WH', barcode: '8941018-2', stock_main: 11, stock_b2: 7, price: 890 },
      { size: 'XL', color: 'Burgundy', sku: 'POL-PIQ-XL-BG', barcode: '8941018-3', stock_main: 10, stock_b2: 6, price: 890 },
    ]
  },
  {
    id: 'p19', sku: 'POL-SLM-M', barcode: '8941019', name_en: 'Slim Fit Tipped Collar Polo', name_bn: 'টিপড কলার স্লিম ফিট পোলো',
    category: 'polo', brand: 'DF Classic', vendor_id: 'v4', price: 990, cost: 530, stock_main: 24, stock_b2: 16,
    reorder_level: 6, initials: 'SP', color: '#1D4ED8',
    variants: [
      { size: 'M', color: 'Black/White', sku: 'POL-SLM-M-BW', barcode: '8941019-1', stock_main: 12, stock_b2: 8, price: 990 },
      { size: 'L', color: 'Navy/Red', sku: 'POL-SLM-L-NR', barcode: '8941019-2', stock_main: 12, stock_b2: 8, price: 990 },
    ]
  },
  {
    id: 'p20', sku: 'POL-JAC-XL', barcode: '8941020', name_en: 'Mercerised Jacquard Polo', name_bn: 'মার্সারাইজড জ্যাককার্ড পোলো',
    category: 'polo', brand: 'DF Elite', vendor_id: 'v4', price: 1450, cost: 820, stock_main: 14, stock_b2: 9,
    reorder_level: 4, initials: 'MP', color: '#D97706',
    variants: [
      { size: 'L', color: 'Royal Green', sku: 'POL-JAC-L-RG', barcode: '8941020-1', stock_main: 7, stock_b2: 5, price: 1450 },
      { size: 'XL', color: 'Midnight Blue', sku: 'POL-JAC-XL-MB', barcode: '8941020-2', stock_main: 7, stock_b2: 4, price: 1450 },
    ]
  },

  // 21-24: Jeans
  {
    id: 'p21', sku: 'JEA-SLM-32', barcode: '8941021', name_en: 'Twill Slim Jeans 32', name_bn: 'টুইল স্লিম ফিট জিন্স ৩২',
    category: 'jeans', brand: 'Bogura Denim Co', vendor_id: 'v8', price: 1890, cost: 1150, stock_main: 24, stock_b2: 15,
    reorder_level: 6, initials: 'SJ', color: '#1D4ED8',
    variants: [
      { size: '30', color: 'Indigo Dark', sku: 'JEA-SLM-30-ID', barcode: '8941021-1', stock_main: 8, stock_b2: 5, price: 1890 },
      { size: '32', color: 'Indigo Dark', sku: 'JEA-SLM-32-ID', barcode: '8941021-2', stock_main: 8, stock_b2: 5, price: 1890 },
      { size: '34', color: 'Medium Wash', sku: 'JEA-SLM-34-MW', barcode: '8941021-3', stock_main: 8, stock_b2: 5, price: 1890 },
    ]
  },
  {
    id: 'p22', sku: 'JEA-REG-34', barcode: '8941022', name_en: 'Regular Fit Classic Blue Denim', name_bn: 'রেগুলার ফিট ক্লাসিক ব্লু ডেনিম',
    category: 'jeans', brand: 'Bogura Denim Co', vendor_id: 'v8', price: 1750, cost: 1050, stock_main: 20, stock_b2: 14,
    reorder_level: 6, initials: 'RJ', color: '#0369A1',
    variants: [
      { size: '32', color: 'Classic Blue', sku: 'JEA-REG-32-CB', barcode: '8941022-1', stock_main: 10, stock_b2: 7, price: 1750 },
      { size: '34', color: 'Classic Blue', sku: 'JEA-REG-34-CB', barcode: '8941022-2', stock_main: 10, stock_b2: 7, price: 1750 },
    ]
  },
  {
    id: 'p23', sku: 'JEA-STR-32', barcode: '8941023', name_en: 'Comfort Stretch Black Denim', name_bn: 'কমফোর্ট স্ট্রেচ কালো জিন্স',
    category: 'jeans', brand: 'DF Denim', vendor_id: 'v8', price: 1990, cost: 1200, stock_main: 18, stock_b2: 11,
    reorder_level: 5, initials: 'BJ', color: '#0F172A',
    variants: [
      { size: '32', color: 'Jet Black', sku: 'JEA-STR-32-JB', barcode: '8941023-1', stock_main: 9, stock_b2: 6, price: 1990 },
      { size: '34', color: 'Jet Black', sku: 'JEA-STR-34-JB', barcode: '8941023-2', stock_main: 9, stock_b2: 5, price: 1990 },
    ]
  },
  {
    id: 'p24', sku: 'JEA-JOG-30', barcode: '8941024', name_en: 'Denim Jogger Pants with Drawstring', name_bn: 'ডেনিম জগার প্যান্ট',
    category: 'jeans', brand: 'DF Youth', vendor_id: 'v8', price: 1650, cost: 980, stock_main: 15, stock_b2: 9,
    reorder_level: 4, initials: 'DJ', color: '#475569',
    variants: [
      { size: '30', color: 'Light Blue', sku: 'JEA-JOG-30-LB', barcode: '8941024-1', stock_main: 7, stock_b2: 4, price: 1650 },
      { size: '32', color: 'Washed Grey', sku: 'JEA-JOG-32-WG', barcode: '8941024-2', stock_main: 8, stock_b2: 5, price: 1650 },
    ]
  },

  // 25-28: Shirt
  {
    id: 'p25', sku: 'SHT-OXF-16', barcode: '8941025', name_en: 'Classic Oxford Shirt 16', name_bn: 'ক্লাসিক অক্সফোর্ড শার্ট ১৬',
    category: 'shirt', brand: 'DF Formal', vendor_id: 'v3', price: 1350, cost: 820, stock_main: 14, stock_b2: 9,
    reorder_level: 4, initials: 'OS', color: '#0F766E',
    variants: [
      { size: '15.5', color: 'Sky Blue', sku: 'SHT-OXF-155-SB', barcode: '8941025-1', stock_main: 7, stock_b2: 4, price: 1350 },
      { size: '16.0', color: 'White', sku: 'SHT-OXF-160-WH', barcode: '8941025-2', stock_main: 7, stock_b2: 5, price: 1350 },
    ]
  },
  {
    id: 'p26', sku: 'SHT-LIN-L', barcode: '8941026', name_en: 'Pure Linen Mandarin Shirt', name_bn: 'পিওর লিনেন ম্যান্ডারিন শার্ট',
    category: 'shirt', brand: 'DF Casual', vendor_id: 'v3', price: 1850, cost: 1100, stock_main: 12, stock_b2: 7,
    reorder_level: 4, initials: 'LS', color: '#D97706',
    variants: [
      { size: 'M', color: 'Olive', sku: 'SHT-LIN-M-OL', barcode: '8941026-1', stock_main: 6, stock_b2: 3, price: 1850 },
      { size: 'L', color: 'Beige', sku: 'SHT-LIN-L-BG', barcode: '8941026-2', stock_main: 6, stock_b2: 4, price: 1850 },
    ]
  },
  {
    id: 'p27', sku: 'SHT-CHK-M', barcode: '8941027', name_en: 'Yarn-Dyed Plaid Check Shirt', name_bn: 'চেক ক্যাজুয়াল শার্ট',
    category: 'shirt', brand: 'DF Casual', vendor_id: 'v3', price: 1250, cost: 720, stock_main: 19, stock_b2: 12,
    reorder_level: 5, initials: 'CS', color: '#BE123C',
    variants: [
      { size: 'M', color: 'Red/Navy Check', sku: 'SHT-CHK-M-RN', barcode: '8941027-1', stock_main: 10, stock_b2: 6, price: 1250 },
      { size: 'L', color: 'Green/Navy Check', sku: 'SHT-CHK-L-GN', barcode: '8941027-2', stock_main: 9, stock_b2: 6, price: 1250 },
    ]
  },
  {
    id: 'p28', sku: 'SHT-SLK-40', barcode: '8941028', name_en: 'Sateen Formal Party Shirt', name_bn: 'সাটিন ফর্মাল পার্টি শার্ট',
    category: 'shirt', brand: 'DF Formal', vendor_id: 'v3', price: 1650, cost: 980, stock_main: 11, stock_b2: 6,
    reorder_level: 3, initials: 'SS', color: '#4338CA',
    variants: [
      { size: 'M', color: 'Silver Grey', sku: 'SHT-SLK-M-SG', barcode: '8941028-1', stock_main: 6, stock_b2: 3, price: 1650 },
      { size: 'L', color: 'Deep Burgundy', sku: 'SHT-SLK-L-DB', barcode: '8941028-2', stock_main: 5, stock_b2: 3, price: 1650 },
    ]
  },

  // 29-31: Kurti
  {
    id: 'p29', sku: 'KUR-EMB-XL', barcode: '8941029', name_en: 'Embroidered Kurti XL', name_bn: 'এমব্রয়ডারি কুর্তি - এক্সএল',
    category: 'kurti', brand: 'DF Women', vendor_id: 'v10', price: 1650, cost: 950, stock_main: 11, stock_b2: 7,
    reorder_level: 3, initials: 'EK', color: '#D97706',
    variants: [
      { size: 'L', color: 'Mustard', sku: 'KUR-EMB-L-MU', barcode: '8941029-1', stock_main: 5, stock_b2: 3, price: 1650 },
      { size: 'XL', color: 'Deep Teal', sku: 'KUR-EMB-XL-DT', barcode: '8941029-2', stock_main: 6, stock_b2: 4, price: 1650 },
    ]
  },
  {
    id: 'p30', sku: 'KUR-RAY-L', barcode: '8941030', name_en: 'Printed Rayon A-Line Kurti', name_bn: 'রেয়ন প্রিন্টেড এ-লাইন কুর্তি',
    category: 'kurti', brand: 'DF Women', vendor_id: 'v10', price: 1350, cost: 780, stock_main: 16, stock_b2: 10,
    reorder_level: 4, initials: 'RK', color: '#BE185D',
    variants: [
      { size: 'M', color: 'Indigo Print', sku: 'KUR-RAY-M-IP', barcode: '8941030-1', stock_main: 8, stock_b2: 5, price: 1350 },
      { size: 'L', color: 'Ruby Red', sku: 'KUR-RAY-L-RR', barcode: '8941030-2', stock_main: 8, stock_b2: 5, price: 1350 },
    ]
  },
  {
    id: 'p31', sku: 'KUR-COT-M', barcode: '8941031', name_en: 'Hand-Stitched Cotton Kurti', name_bn: 'হস্তশিল্প সুতি কুর্তি',
    category: 'kurti', brand: 'Aarong Craft', vendor_id: 'v1', price: 1950, cost: 1200, stock_main: 9, stock_b2: 6,
    reorder_level: 3, initials: 'HK', color: '#15803D',
    variants: [
      { size: 'M', color: 'Off-White', sku: 'KUR-COT-M-OW', barcode: '8941031-1', stock_main: 5, stock_b2: 3, price: 1950 },
      { size: 'L', color: 'Pastel Peach', sku: 'KUR-COT-L-PP', barcode: '8941031-2', stock_main: 4, stock_b2: 3, price: 1950 },
    ]
  },

  // 32-34: Hijab
  {
    id: 'p32', sku: 'HIJ-SLK-01', barcode: '8941032', name_en: 'Pure Silk Premium Hijab', name_bn: 'পিওর সিল্ক হিজাব',
    category: 'hijab', brand: 'DF Modest', vendor_id: 'v10', price: 750, cost: 400, stock_main: 19, stock_b2: 25,
    reorder_level: 6, initials: 'SH', color: '#15803D',
    variants: [
      { size: 'Standard', color: 'Mocha', sku: 'HIJ-SLK-MC', barcode: '8941032-1', stock_main: 10, stock_b2: 13, price: 750 },
      { size: 'Standard', color: 'Champagne', sku: 'HIJ-SLK-CP', barcode: '8941032-2', stock_main: 9, stock_b2: 12, price: 750 },
    ]
  },
  {
    id: 'p33', sku: 'HIJ-CHF-02', barcode: '8941033', name_en: 'Bubble Chiffon Textured Hijab', name_bn: 'বাবল শিফন টেক্সচার্ড হিজাব',
    category: 'hijab', brand: 'DF Modest', vendor_id: 'v10', price: 450, cost: 230, stock_main: 35, stock_b2: 28,
    reorder_level: 10, initials: 'BH', color: '#6D28D9',
    variants: [
      { size: 'Standard', color: 'Dusty Rose', sku: 'HIJ-CHF-DR', barcode: '8941033-1', stock_main: 18, stock_b2: 14, price: 450 },
      { size: 'Standard', color: 'Charcoal', sku: 'HIJ-CHF-CH', barcode: '8941033-2', stock_main: 17, stock_b2: 14, price: 450 },
    ]
  },
  {
    id: 'p34', sku: 'HIJ-CRN-03', barcode: '8941034', name_en: 'Cotton Crinkle Instant Hijab', name_bn: 'সুতি ক্রিঙ্কল ইনস্ট্যান্ট হিজাব',
    category: 'hijab', brand: 'DF Modest', vendor_id: 'v10', price: 550, cost: 280, stock_main: 22, stock_b2: 16,
    reorder_level: 8, initials: 'CH', color: '#0369A1',
    variants: [
      { size: 'Standard', color: 'Olive', sku: 'HIJ-CRN-OL', barcode: '8941034-1', stock_main: 11, stock_b2: 8, price: 550 },
      { size: 'Standard', color: 'Espresso', sku: 'HIJ-CRN-ES', barcode: '8941034-2', stock_main: 11, stock_b2: 8, price: 550 },
    ]
  },

  // 35-37: Shoes
  {
    id: 'p35', sku: 'SHOE-LEA-42', barcode: '8941035', name_en: 'Formal Leather Loafer 42', name_bn: 'লেদার ফর্মাল লোফার ৪২',
    category: 'shoes', brand: 'Apex Footwear', vendor_id: 'v5', price: 3800, cost: 2300, stock_main: 5, stock_b2: 2,
    reorder_level: 2, initials: 'FL', color: '#475569',
    variants: [
      { size: '41', color: 'Dark Tan', sku: 'SHOE-LEA-41-DT', barcode: '8941035-1', stock_main: 2, stock_b2: 1, price: 3800 },
      { size: '42', color: 'Dark Tan', sku: 'SHOE-LEA-42-DT', barcode: '8941035-2', stock_main: 3, stock_b2: 1, price: 3800 },
    ]
  },
  {
    id: 'p36', sku: 'SHOE-OXF-41', barcode: '8941036', name_en: 'Classic Oxford Dress Shoes', name_bn: 'ক্লাসিক অক্সফোর্ড ড্রেস শুজ',
    category: 'shoes', brand: 'Apex Footwear', vendor_id: 'v5', price: 4500, cost: 2750, stock_main: 4, stock_b2: 3,
    reorder_level: 2, initials: 'OD', color: '#0F172A',
    variants: [
      { size: '41', color: 'Black', sku: 'SHOE-OXF-41-BK', barcode: '8941036-1', stock_main: 2, stock_b2: 2, price: 4500 },
      { size: '42', color: 'Black', sku: 'SHOE-OXF-42-BK', barcode: '8941036-2', stock_main: 2, stock_b2: 1, price: 4500 },
    ]
  },
  {
    id: 'p37', sku: 'SHOE-CAS-42', barcode: '8941037', name_en: 'Breathable Casual Sneaker', name_bn: 'ক্যাজুয়াল স্নিকার জুতা',
    category: 'shoes', brand: 'DF Steps', vendor_id: 'v5', price: 2450, cost: 1450, stock_main: 12, stock_b2: 8,
    reorder_level: 4, initials: 'CS', color: '#0F766E',
    variants: [
      { size: '41', color: 'White/Grey', sku: 'SHOE-CAS-41-WG', barcode: '8941037-1', stock_main: 6, stock_b2: 4, price: 2450 },
      { size: '42', color: 'Navy/White', sku: 'SHOE-CAS-42-NW', barcode: '8941037-2', stock_main: 6, stock_b2: 4, price: 2450 },
    ]
  },

  // 38-39: Belts
  {
    id: 'p38', sku: 'BLT-GEN-01', barcode: '8941038', name_en: 'Genuine Leather Belt Black', name_bn: 'জেনুইন লেদার বেল্ট - কালো',
    category: 'belts', brand: 'DF Leather', vendor_id: 'v5', price: 650, cost: 350, stock_main: 22, stock_b2: 14,
    reorder_level: 6, initials: 'LB', color: '#334155',
    variants: [
      { size: '34-36', color: 'Black', sku: 'BLT-GEN-34-BK', barcode: '8941038-1', stock_main: 11, stock_b2: 7, price: 650 },
      { size: '38-40', color: 'Black', sku: 'BLT-GEN-38-BK', barcode: '8941038-2', stock_main: 11, stock_b2: 7, price: 650 },
    ]
  },
  {
    id: 'p39', sku: 'BLT-REV-02', barcode: '8941039', name_en: 'Reversible Dual-Tone Belt', name_bn: 'রিভার্সিবল ডুয়াল-টোন বেল্ট',
    category: 'belts', brand: 'DF Leather', vendor_id: 'v5', price: 850, cost: 450, stock_main: 16, stock_b2: 11,
    reorder_level: 5, initials: 'RB', color: '#D97706',
    variants: [
      { size: 'Free', color: 'Black/Brown', sku: 'BLT-REV-BB', barcode: '8941039-1', stock_main: 16, stock_b2: 11, price: 850 },
    ]
  },

  // 40-42: Kids wear
  {
    id: 'p40', sku: 'KID-EID-04', barcode: '8941040', name_en: 'Kids Festive Panjabi 4-5Y', name_bn: 'বাচ্চাদের ঈদ পাঞ্জাবি সেট',
    category: 'kids', brand: 'DF Juniors', vendor_id: 'v1', price: 1150, cost: 680, stock_main: 0, stock_b2: 0,
    reorder_level: 5, initials: 'KP', color: '#1D4ED8',
    variants: [
      { size: '4-5Y', color: 'Mustard', sku: 'KID-EID-45-MU', barcode: '8941040-1', stock_main: 0, stock_b2: 0, price: 1150 },
      { size: '6-7Y', color: 'Royal Blue', sku: 'KID-EID-67-RB', barcode: '8941040-2', stock_main: 0, stock_b2: 0, price: 1150 },
    ]
  },
  {
    id: 'p41', sku: 'KID-FRK-03', barcode: '8941041', name_en: 'Girls Party Frock 5-6Y', name_bn: 'মেয়েদের পার্টি ফ্রক ৫-৬ বছর',
    category: 'kids', brand: 'DF Juniors', vendor_id: 'v10', price: 1450, cost: 850, stock_main: 14, stock_b2: 8,
    reorder_level: 4, initials: 'GF', color: '#BE185D',
    variants: [
      { size: '5-6Y', color: 'Pink Tutu', sku: 'KID-FRK-56-PK', barcode: '8941041-1', stock_main: 7, stock_b2: 4, price: 1450 },
      { size: '7-8Y', color: 'Powder Blue', sku: 'KID-FRK-78-PB', barcode: '8941041-2', stock_main: 7, stock_b2: 4, price: 1450 },
    ]
  },
  {
    id: 'p42', sku: 'KID-DEN-02', barcode: '8941042', name_en: 'Boys Denim Dungaree Set', name_bn: 'বাচ্চাদের ডেনিম ডাংগরি সেট',
    category: 'kids', brand: 'DF Juniors', vendor_id: 'v8', price: 1350, cost: 790, stock_main: 12, stock_b2: 7,
    reorder_level: 4, initials: 'DD', color: '#0369A1',
    variants: [
      { size: '3-4Y', color: 'Denim/Red', sku: 'KID-DEN-34-DR', barcode: '8941042-1', stock_main: 6, stock_b2: 4, price: 1350 },
      { size: '5-6Y', color: 'Denim/Yellow', sku: 'KID-DEN-56-DY', barcode: '8941042-2', stock_main: 6, stock_b2: 3, price: 1350 },
    ]
  }
];

// 32 Customers with 8-digit Luhn check digit, dues, cards, PIN statuses
const CUSTOMERS = [
  { id: 'c1', customer_no: '1000 0017', name_en: 'Rahim Uddin', name_bn: 'রহিম উদ্দিন', phone: '01711-234456', masked_phone: '••• 456', group: 'Retail', balance: -1200, card_status: 'Active', pin_status: 'Verified', division: 'Rajshahi', district: 'Joypurhat', city_upazila: 'Kalai', member_since: '10/2024', last_purchase: '2 days ago', total_spent: 48600, visits: 18, avg_basket: 2700, credit_limit: 10000, birthday: '14 Nov' },
  { id: 'c2', customer_no: '1000 0025', name_en: 'Farzana Akter', name_bn: 'ফারজানা আক্তার', phone: '01819-876543', masked_phone: '••• 543', group: 'VIP Wholesale', balance: 0, card_status: 'Active', pin_status: 'Set', division: 'Dhaka', district: 'Dhaka', city_upazila: 'Mirpur', member_since: '01/2025', last_purchase: 'Today', total_spent: 184500, visits: 24, avg_basket: 7687, credit_limit: 50000, birthday: '22 Mar' },
  { id: 'c3', customer_no: '1000 0033', name_en: 'Tanvir Ahmed', name_bn: 'তানভীর আহমেদ', phone: '01912-334455', masked_phone: '••• 455', group: 'Retail', balance: -4500, card_status: 'Lost', pin_status: 'Locked', division: 'Rajshahi', district: 'Bogura', city_upazila: 'Sadar', member_since: '05/2024', last_purchase: '18 Oct 2026', total_spent: 32000, visits: 9, avg_basket: 3550, credit_limit: 5000, birthday: '05 Oct' },
  { id: 'c4', customer_no: '1000 0041', name_en: 'Sadia Islam', name_bn: 'সাদিয়া ইসলাম', phone: '01611-002233', masked_phone: '••• 233', group: 'Retail', balance: 0, card_status: 'None', pin_status: 'Not set', division: 'Dhaka', district: 'Dhaka', city_upazila: 'Uttara', member_since: '09/2026', last_purchase: 'Yesterday', total_spent: 12400, visits: 4, avg_basket: 3100, credit_limit: 5000, birthday: '30 Dec' },
  { id: 'c5', customer_no: '1000 0058', name_en: 'Mohammad Hossain', name_bn: 'মোহাম্মদ হোসেন', phone: '01712-998877', masked_phone: '••• 877', group: 'Retail', balance: -850, card_status: 'Active', pin_status: 'Verified', division: 'Rajshahi', district: 'Joypurhat', city_upazila: 'Panchbibi', member_since: '03/2025', last_purchase: '3 days ago', total_spent: 26800, visits: 8, avg_basket: 3350, credit_limit: 5000, birthday: '12 Jan' },
  { id: 'c6', customer_no: '1000 0066', name_en: 'Nazmul Haque', name_bn: 'নাজমুল হক', phone: '01814-112244', masked_phone: '••• 244', group: 'Corporate', balance: -15400, card_status: 'Active', pin_status: 'Set', division: 'Dhaka', district: 'Dhaka', city_upazila: 'Gulshan', member_since: '11/2024', last_purchase: '15 Oct 2026', total_spent: 342000, visits: 14, avg_basket: 24428, credit_limit: 50000, birthday: '18 Aug' },
  { id: 'c7', customer_no: '1000 0074', name_en: 'Roksana Parvin', name_bn: 'রোকসানা পারভীন', phone: '01715-334466', masked_phone: '••• 466', group: 'Retail', balance: 0, card_status: 'Active', pin_status: 'Verified', division: 'Rajshahi', district: 'Naogaon', city_upazila: 'Dhamoirhat', member_since: '06/2025', last_purchase: '5 days ago', total_spent: 19500, visits: 6, avg_basket: 3250, credit_limit: 5000, birthday: '28 Feb' },
  { id: 'c8', customer_no: '1000 0082', name_en: 'Ariful Islam', name_bn: 'আরিফুল ইসলাম', phone: '01918-556677', masked_phone: '••• 677', group: 'Retail', balance: -2400, card_status: 'Active', pin_status: 'Set', division: 'Rajshahi', district: 'Joypurhat', city_upazila: 'Khetlal', member_since: '08/2025', last_purchase: '20 Oct 2026', total_spent: 17200, visits: 5, avg_basket: 3440, credit_limit: 5000, birthday: '10 May' },
  { id: 'c9', customer_no: '1000 0090', name_en: 'Jannatul Ferdous', name_bn: 'জান্নাতুল ফেরদৌস', phone: '01619-778899', masked_phone: '••• 899', group: 'VIP Wholesale', balance: 0, card_status: 'Active', pin_status: 'Verified', division: 'Rangpur', district: 'Dinajpur', city_upazila: 'Kotwali', member_since: '02/2025', last_purchase: '1 week ago', total_spent: 98000, visits: 11, avg_basket: 8909, credit_limit: 30000, birthday: '15 Jul' },
  { id: 'c10', customer_no: '1000 0108', name_en: 'Shakil Ahmed', name_bn: 'শাকিল আহমেদ', phone: '01720-990011', masked_phone: '••• 011', group: 'Retail', balance: -3100, card_status: 'Lost', pin_status: 'Locked', division: 'Rajshahi', district: 'Bogura', city_upazila: 'Sherpur', member_since: '12/2024', last_purchase: '22 Oct 2026', total_spent: 24500, visits: 7, avg_basket: 3500, credit_limit: 5000, birthday: '03 Sep' },
  { id: 'c11', customer_no: '1000 0116', name_en: 'Tamanna Rahman', name_bn: 'তামান্না রহমান', phone: '01822-113355', masked_phone: '••• 355', group: 'Retail', balance: 0, card_status: 'Active', pin_status: 'Verified', division: 'Dhaka', district: 'Dhaka', city_upazila: 'Dhanmondi', member_since: '04/2025', last_purchase: 'Today', total_spent: 31200, visits: 9, avg_basket: 3466, credit_limit: 10000, birthday: '19 Oct' },
  { id: 'c12', customer_no: '1000 0124', name_en: 'Kawsar Mahmud', name_bn: 'কাওসার মাহমুদ', phone: '01925-224466', masked_phone: '••• 466', group: 'Retail', balance: -650, card_status: 'Active', pin_status: 'Set', division: 'Rajshahi', district: 'Joypurhat', city_upazila: 'Kalai', member_since: '07/2025', last_purchase: '4 days ago', total_spent: 15400, visits: 5, avg_basket: 3080, credit_limit: 5000, birthday: '25 Jun' },
  { id: 'c13', customer_no: '1000 0132', name_en: 'Mehedi Hasan', name_bn: 'মেহেদী হাসান', phone: '01726-335577', masked_phone: '••• 577', group: 'Retail', balance: 0, card_status: 'None', pin_status: 'Not set', division: 'Rajshahi', district: 'Rajshahi', city_upazila: 'Boalia', member_since: '10/2025', last_purchase: '12 Oct 2026', total_spent: 8900, visits: 3, avg_basket: 2966, credit_limit: 5000, birthday: '01 Apr' },
  { id: 'c14', customer_no: '1000 0140', name_en: 'Nusrat Jahan', name_bn: 'নুসরাত জাহান', phone: '01628-446688', masked_phone: '••• 688', group: 'VIP Wholesale', balance: -18200, card_status: 'Active', pin_status: 'Verified', division: 'Dhaka', district: 'Gazipur', city_upazila: 'Joydebpur', member_since: '01/2025', last_purchase: 'Yesterday', total_spent: 215000, visits: 16, avg_basket: 13437, credit_limit: 40000, birthday: '11 Nov' },
  { id: 'c15', customer_no: '1000 0157', name_en: 'Habibur Rahman', name_bn: 'হাবিবুর রহমান', phone: '01730-557799', masked_phone: '••• 799', group: 'Retail', balance: 0, card_status: 'Active', pin_status: 'Set', division: 'Rajshahi', district: 'Joypurhat', city_upazila: 'Akkelpur', member_since: '05/2025', last_purchase: '1 week ago', total_spent: 19800, visits: 6, avg_basket: 3300, credit_limit: 5000, birthday: '07 Dec' },
  { id: 'c16', customer_no: '1000 0165', name_en: 'Shirin Sultana', name_bn: 'শিরিন সুলতানা', phone: '01833-668800', masked_phone: '••• 800', group: 'Retail', balance: -2900, card_status: 'Active', pin_status: 'Verified', division: 'Rangpur', district: 'Rangpur', city_upazila: 'Kotwali', member_since: '08/2025', last_purchase: '25 Oct 2026', total_spent: 28400, visits: 8, avg_basket: 3550, credit_limit: 5000, birthday: '16 Mar' },
  { id: 'c17', customer_no: '1000 0173', name_en: 'Ziaul Karim', name_bn: 'জিয়াউল করিম', phone: '01936-779911', masked_phone: '••• 911', group: 'Corporate', balance: -8900, card_status: 'Active', pin_status: 'Set', division: 'Dhaka', district: 'Dhaka', city_upazila: 'Tejgaon', member_since: '11/2024', last_purchase: '19 Oct 2026', total_spent: 164000, visits: 10, avg_basket: 16400, credit_limit: 30000, birthday: '29 Jul' },
  { id: 'c18', customer_no: '1000 0181', name_en: 'Sharmin Akter', name_bn: 'শারমিন আক্তার', phone: '01739-880022', masked_phone: '••• 022', group: 'Retail', balance: 0, card_status: 'Active', pin_status: 'Verified', division: 'Rajshahi', district: 'Bogura', city_upazila: 'Shibganj', member_since: '03/2025', last_purchase: '3 days ago', total_spent: 22100, visits: 7, avg_basket: 3157, credit_limit: 5000, birthday: '09 Sep' },
  { id: 'c19', customer_no: '1000 0199', name_en: 'Asif Iqbal', name_bn: 'আসিফ ইকবাল', phone: '01842-991133', masked_phone: '••• 133', group: 'Retail', balance: -1500, card_status: 'None', pin_status: 'Not set', division: 'Rajshahi', district: 'Joypurhat', city_upazila: 'Kalai', member_since: '09/2025', last_purchase: 'Yesterday', total_spent: 14200, visits: 4, avg_basket: 3550, credit_limit: 5000, birthday: '14 Feb' },
  { id: 'c20', customer_no: '1000 0207', name_en: 'Mahbuba Begum', name_bn: 'মাহবুবা বেগম', phone: '01945-002244', masked_phone: '••• 244', group: 'Retail', balance: 0, card_status: 'Active', pin_status: 'Verified', division: 'Rajshahi', district: 'Naogaon', city_upazila: 'Patnitala', member_since: '06/2025', last_purchase: '1 week ago', total_spent: 18700, visits: 6, avg_basket: 3116, credit_limit: 5000, birthday: '23 Aug' },
  { id: 'c21', customer_no: '1000 0215', name_en: 'Fahim Faisal', name_bn: 'ফাহিম ফয়সাল', phone: '01648-113355', masked_phone: '••• 355', group: 'Retail', balance: -4200, card_status: 'Lost', pin_status: 'Locked', division: 'Dhaka', district: 'Dhaka', city_upazila: 'Mohammadpur', member_since: '02/2025', last_purchase: '16 Oct 2026', total_spent: 27900, visits: 7, avg_basket: 3985, credit_limit: 5000, birthday: '04 Jun' },
  { id: 'c22', customer_no: '1000 0223', name_en: 'Sabrina Mostofa', name_bn: 'সাবরিনা মোস্তফা', phone: '01751-224466', masked_phone: '••• 466', group: 'Retail', balance: 0, card_status: 'Active', pin_status: 'Verified', division: 'Rajshahi', district: 'Joypurhat', city_upazila: 'Kalai', member_since: '05/2025', last_purchase: 'Today', total_spent: 33400, visits: 10, avg_basket: 3340, credit_limit: 10000, birthday: '17 Jan' },
  { id: 'c23', customer_no: '1000 0231', name_en: 'Delwar Hossain', name_bn: 'দেলোয়ার হোসেন', phone: '01854-335577', masked_phone: '••• 577', group: 'Retail', balance: -800, card_status: 'Active', pin_status: 'Set', division: 'Rajshahi', district: 'Bogura', city_upazila: 'Kahaloo', member_since: '08/2025', last_purchase: '2 days ago', total_spent: 16800, visits: 5, avg_basket: 3360, credit_limit: 5000, birthday: '31 Oct' },
  { id: 'c24', customer_no: '1000 0249', name_en: 'Anika Tabassum', name_bn: 'আনিকা তাবাসসুম', phone: '01957-446688', masked_phone: '••• 688', group: 'VIP Wholesale', balance: 0, card_status: 'Active', pin_status: 'Verified', division: 'Dhaka', district: 'Narayanganj', city_upazila: 'Sadar', member_since: '11/2024', last_purchase: '26 Oct 2026', total_spent: 148000, visits: 12, avg_basket: 12333, credit_limit: 30000, birthday: '08 Dec' },
  { id: 'c25', customer_no: '1000 0256', name_en: 'Mustafizur Rahman', name_bn: 'মুস্তাফিজুর রহমান', phone: '01760-557799', masked_phone: '••• 799', group: 'Retail', balance: -5200, card_status: 'Active', pin_status: 'Set', division: 'Rajshahi', district: 'Joypurhat', city_upazila: 'Panchbibi', member_since: '04/2025', last_purchase: '14 Oct 2026', total_spent: 31500, visits: 8, avg_basket: 3937, credit_limit: 10000, birthday: '21 May' },
  { id: 'c26', customer_no: '1000 0264', name_en: 'Sultana Razia', name_bn: 'সুলতানা রাজিয়া', phone: '01863-668800', masked_phone: '••• 800', group: 'Retail', balance: 0, card_status: 'None', pin_status: 'Not set', division: 'Rajshahi', district: 'Naogaon', city_upazila: 'Badalgachhi', member_since: '09/2025', last_purchase: '5 days ago', total_spent: 11200, visits: 4, avg_basket: 2800, credit_limit: 5000, birthday: '13 Jul' },
  { id: 'c27', customer_no: '1000 0272', name_en: 'Rezaul Karim', name_bn: 'রেজাউল করিম', phone: '01966-779911', masked_phone: '••• 911', group: 'Retail', balance: -1900, card_status: 'Active', pin_status: 'Verified', division: 'Rangpur', district: 'Gaibandha', city_upazila: 'Gobindaganj', member_since: '07/2025', last_purchase: '23 Oct 2026', total_spent: 24100, visits: 7, avg_basket: 3442, credit_limit: 5000, birthday: '26 Feb' },
  { id: 'c28', customer_no: '1000 0280', name_en: 'Bilkis Banu', name_bn: 'বিলকিস বানু', phone: '01769-880022', masked_phone: '••• 022', group: 'Retail', balance: 0, card_status: 'Active', pin_status: 'Set', division: 'Rajshahi', district: 'Joypurhat', city_upazila: 'Kalai', member_since: '03/2025', last_purchase: 'Today', total_spent: 29500, visits: 9, avg_basket: 3277, credit_limit: 5000, birthday: '19 Apr' },
  { id: 'c29', customer_no: '1000 0298', name_en: 'Saiful Islam', name_bn: 'সাইফুল ইসলাম', phone: '01872-991133', masked_phone: '••• 133', group: 'Corporate', balance: -11200, card_status: 'Active', pin_status: 'Verified', division: 'Dhaka', district: 'Dhaka', city_upazila: 'Motijheel', member_since: '12/2024', last_purchase: '17 Oct 2026', total_spent: 275000, visits: 13, avg_basket: 21153, credit_limit: 40000, birthday: '02 Nov' },
  { id: 'c30', customer_no: '1000 0306', name_en: 'Morium Khatun', name_bn: 'মরিয়ম খাতুন', phone: '01975-002244', masked_phone: '••• 244', group: 'Retail', balance: 0, card_status: 'Active', pin_status: 'Verified', division: 'Rajshahi', district: 'Bogura', city_upazila: 'Gabtali', member_since: '06/2025', last_purchase: '6 days ago', total_spent: 18400, visits: 6, avg_basket: 3066, credit_limit: 5000, birthday: '15 Aug' },
  { id: 'c31', customer_no: '1000 0314', name_en: 'Ashraful Alam', name_bn: 'আশরাফুল আলম', phone: '01678-113355', masked_phone: '••• 355', group: 'Retail', balance: -750, card_status: 'Active', pin_status: 'Set', division: 'Rajshahi', district: 'Joypurhat', city_upazila: 'Kalai', member_since: '10/2025', last_purchase: 'Yesterday', total_spent: 9600, visits: 3, avg_basket: 3200, credit_limit: 5000, birthday: '27 Sep' },
  { id: 'c32', customer_no: '1000 0322', name_en: 'Farhana Yeasmin', name_bn: 'ফারহানা ইয়াসমিন', phone: '01781-224466', masked_phone: '••• 466', group: 'Retail', balance: 0, card_status: 'Active', pin_status: 'Verified', division: 'Dhaka', district: 'Dhaka', city_upazila: 'Mirpur', member_since: '05/2025', last_purchase: '3 days ago', total_spent: 21800, visits: 7, avg_basket: 3114, credit_limit: 5000, birthday: '06 Dec' },
];

// 10 Bangladeshi Apparel Vendors
const VENDORS = [
  { id: 'v1', name: 'Aarong Craft Suppliers Ltd', location: 'Dhaka', contact: '01711-112233', payable: 45000, bills_count: 8, last_order: '22 Oct 2026' },
  { id: 'v2', name: 'Noman Fabrics & Weaving', location: 'Gazipur', contact: '01819-223344', payable: 120000, bills_count: 14, last_order: '26 Oct 2026' },
  { id: 'v3', name: 'Beximco Textiles Distributor', location: 'Narayanganj', contact: '01912-334455', payable: 85000, bills_count: 10, last_order: '18 Oct 2026' },
  { id: 'v4', name: 'Square Fashion Knitwear', location: 'Bhaluka', contact: '01611-445566', payable: 62000, bills_count: 7, last_order: '25 Oct 2026' },
  { id: 'v5', name: 'Apex Footwear Distribution', location: 'Dhaka', contact: '01722-556677', payable: 38000, bills_count: 5, last_order: '20 Oct 2026' },
  { id: 'v6', name: 'Tangail Weavers Cooperative', location: 'Tangail', contact: '01833-667788', payable: 24000, bills_count: 6, last_order: '15 Oct 2026' },
  { id: 'v7', name: 'Joypurhat Cotton Mills Agency', location: 'Joypurhat', contact: '01944-778899', payable: 52000, bills_count: 9, last_order: '27 Oct 2026' },
  { id: 'v8', name: 'Bogura Denim Wash & Stitch', location: 'Bogura', contact: '01755-889900', payable: 31000, bills_count: 6, last_order: '21 Oct 2026' },
  { id: 'v9', name: 'Islampur Cloth Merchants', location: 'Old Dhaka', contact: '01866-990011', payable: 94000, bills_count: 12, last_order: '28 Oct 2026' },
  { id: 'v10', name: 'Pakiza Dyeing & Printing', location: 'Savar', contact: '01977-001122', payable: 73000, bills_count: 9, last_order: '24 Oct 2026' },
];

module.exports = { CATEGORIES, PRODUCTS, CUSTOMERS, VENDORS };
