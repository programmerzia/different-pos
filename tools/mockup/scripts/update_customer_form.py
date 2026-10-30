# scripts/update_customer_form.py

with open('scripts/parts/render_customers_messaging.py', 'r', encoding='utf-8') as f:
    content = f.read()

start_marker = "{/* ================= SCREEN 7: CUSTOMER ADD / EDIT FORM ================= */}"
end_marker = "{/* ================= SCREEN 8: CUSTOMER 360 PROFILE ================= */}"

start_idx = content.find(start_marker)
end_idx = content.find(end_marker)

if start_idx == -1 or end_idx == -1:
    print(f"Error finding markers: start={start_idx}, end={end_idx}")
    exit(1)

upgraded_customer_form = '''{/* ================= SCREEN 7: CUSTOMER ADD / EDIT FORM ================= */}
              {currentScreen === 'customer_form' && (
                <div className="flex-1 flex flex-col overflow-y-auto p-4 space-y-4 max-w-4xl mx-auto w-full">
                  
                  {/* Top Bar Navigation & Save */}
                  <div className="flex items-center justify-between pb-3 border-b border-[var(--border)]">
                    <div className="flex items-center gap-3">
                      <button
                        type="button"
                        onClick={() => setCurrentScreen('customers')}
                        className="p-1.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] hover:bg-[var(--surface-sunken)] transition-colors"
                      >
                        <Icon name="arrowLeft" size={16} />
                      </button>
                      <div>
                        <h1 className="text-lg font-bold text-[var(--foreground)]">New Customer Registration — নতুন গ্রাহক নিবন্ধন</h1>
                        <div className="text-xs text-[var(--muted-foreground)]">Assign automatic 8-digit membership card number with Luhn check digit (#1000 00XX)</div>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        onClick={() => setCurrentScreen('customers')}
                        className="h-8 px-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]"
                      >
                        Cancel
                      </button>
                      <button
                        type="button"
                        onClick={() => {
                          // Comprehensive Form Validation
                          const errors = {};
                          const cleanName = (custFormNameEn || '').trim();
                          const cleanPhone = (custFormPhone || '').replace(/[^0-9]/g, '');

                          if (!cleanName || cleanName.length < 3) {
                            errors.name = "Full name is required (at least 3 characters)";
                          }

                          if (!cleanPhone) {
                            errors.phone = "Mobile number is required";
                          } else if (!/^01[3-9]\\d{8}$/.test(cleanPhone)) {
                            errors.phone = "Invalid Bangladesh mobile number (must be 11 digits starting with 013-019)";
                          }

                          if (!custFormStreet || !custFormStreet.trim()) {
                            errors.street = "Street address or holding number is required";
                          }

                          if (custFormNid && custFormNid.trim()) {
                            const nidLen = custFormNid.trim().replace(/[^0-9]/g, '').length;
                            if (![10, 13, 17].includes(nidLen)) {
                              errors.nid = "NID must be 10, 13, or 17 digits";
                            }
                          }

                          if (Number(custFormCreditLimit) < 0) {
                            errors.credit = "Credit limit cannot be negative";
                          }

                          setCustFormErrors(errors);
                          setCustFormTouched({ name: true, phone: true, street: true, nid: true });

                          if (Object.keys(errors).length > 0) {
                            addToast("Please correct the highlighted form errors before saving", "danger");
                            return;
                          }

                          // Success: create and append customer
                          const nextNo = "1000 00" + Math.floor(40 + Math.random() * 50);
                          const newCust = {
                            id: 'c_' + Date.now(),
                            customer_no: nextNo,
                            name_en: cleanName,
                            name_bn: custFormNameBn.trim() || cleanName,
                            phone: cleanPhone,
                            alt_phone: custFormAltPhone || '',
                            nid: custFormNid || '',
                            email: custFormEmail || '',
                            group: custFormGroup,
                            group_discount: custFormGroup.includes('VIP') ? 10 : custFormGroup.includes('Corporate') ? 5 : 0,
                            balance: 0,
                            credit_limit: Number(custFormCreditLimit) || 10000,
                            credit_used: 0,
                            card_status: 'Active',
                            pin_status: 'Set',
                            division: custFormDivision,
                            district: custFormDistrict,
                            city_upazila: custFormUpazila,
                            postal_code: custFormPostCode,
                            street_address: custFormStreet,
                            landmark: custFormLandmark,
                            geo_lat: custFormGeoLat,
                            geo_lng: custFormGeoLng,
                            member_since: 'Today',
                            last_purchase: 'None',
                            total_spent: 0,
                            visits: 0,
                            open_invoices: []
                          };

                          MOCK_CUSTOMERS.unshift(newCust);
                          setSelectedCustomer(newCust);
                          addToast(`Customer "${newCust.name_en}" registered with Member #${newCust.customer_no}!`, "success");
                          setCurrentScreen('customer_profile');
                        }}
                        className="h-8 px-4 rounded-lg bg-[var(--primary)] hover:bg-[var(--primary-hover)] text-white text-xs font-bold shadow-xs transition-all flex items-center gap-1.5 cursor-pointer"
                      >
                        <Icon name="checkCircle" size={14} />
                        <span>Save Customer & Issue Card (গ্রাহক সংরক্ষণ করুন)</span>
                      </button>
                    </div>
                  </div>

                  {/* Form Container */}
                  <div className="space-y-4">
                    
                    {/* SECTION 1: PERSONAL IDENTITY */}
                    <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs space-y-3">
                      <div className="flex items-center justify-between border-b border-[var(--border)] pb-2">
                        <div className="flex items-center gap-2 font-bold text-xs text-[var(--foreground)] uppercase tracking-wider">
                          <Icon name="user" size={15} className="text-[var(--primary)]" />
                          <span>1. Personal Identity & Contact Information (ব্যক্তিগত পরিচিতি)</span>
                        </div>
                        <span className="text-[10px] text-[var(--muted-foreground)]">* Required fields</span>
                      </div>

                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Full Name (English) *</label>
                          <input
                            type="text"
                            placeholder="e.g. Asif Mahmud"
                            value={custFormNameEn}
                            onChange={(e) => {
                              setCustFormNameEn(e.target.value);
                              if (custFormErrors.name) setCustFormErrors(prev => ({ ...prev, name: null }));
                            }}
                            className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs text-[var(--foreground)] ${
                              custFormErrors.name ? 'border-rose-500 bg-rose-500/5' : 'border-[var(--border)]'
                            }`}
                          />
                          {custFormErrors.name && (
                            <div className="text-[10px] text-rose-500 font-semibold mt-1 flex items-center gap-1">
                              <Icon name="alertCircle" size={11} />
                              <span>{custFormErrors.name}</span>
                            </div>
                          )}
                        </div>

                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Full Name (বাংলা)</label>
                          <input
                            type="text"
                            placeholder="e.g. আসিফ মাহমুদ"
                            value={custFormNameBn}
                            onChange={(e) => setCustFormNameBn(e.target.value)}
                            className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                          />
                        </div>

                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Primary Mobile Number (WhatsApp / SMS) *</label>
                          <input
                            type="text"
                            placeholder="01711-234567"
                            value={custFormPhone}
                            onChange={(e) => {
                              setCustFormPhone(e.target.value);
                              if (custFormErrors.phone) setCustFormErrors(prev => ({ ...prev, phone: null }));
                            }}
                            className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs font-mono font-semibold text-[var(--foreground)] ${
                              custFormErrors.phone ? 'border-rose-500 bg-rose-500/5' : 'border-[var(--border)]'
                            }`}
                          />
                          {custFormErrors.phone ? (
                            <div className="text-[10px] text-rose-500 font-semibold mt-1 flex items-center gap-1">
                              <Icon name="alertCircle" size={11} />
                              <span>{custFormErrors.phone}</span>
                            </div>
                          ) : (
                            <div className="text-[10px] text-[var(--muted-foreground)] mt-0.5">Format: 11 digits starting with 013, 014, 015, 016, 017, 018, 019</div>
                          )}
                        </div>

                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Secondary / Alternative Phone</label>
                          <input
                            type="text"
                            placeholder="01822-XXXXXX (Optional)"
                            value={custFormAltPhone}
                            onChange={(e) => setCustFormAltPhone(e.target.value)}
                            className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)]"
                          />
                        </div>

                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">National ID (NID) / Smart Card</label>
                          <input
                            type="text"
                            placeholder="10, 13, or 17 digit NID number"
                            value={custFormNid}
                            onChange={(e) => {
                              setCustFormNid(e.target.value);
                              if (custFormErrors.nid) setCustFormErrors(prev => ({ ...prev, nid: null }));
                            }}
                            className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs font-mono text-[var(--foreground)] ${
                              custFormErrors.nid ? 'border-rose-500 bg-rose-500/5' : 'border-[var(--border)]'
                            }`}
                          />
                          {custFormErrors.nid && (
                            <div className="text-[10px] text-rose-500 font-semibold mt-1 flex items-center gap-1">
                              <Icon name="alertCircle" size={11} />
                              <span>{custFormErrors.nid}</span>
                            </div>
                          )}
                        </div>

                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Email Address</label>
                          <input
                            type="email"
                            placeholder="customer@example.com (Optional)"
                            value={custFormEmail}
                            onChange={(e) => setCustFormEmail(e.target.value)}
                            className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                          />
                        </div>
                      </div>
                    </div>

                    {/* SECTION 2: GEOGRAPHICAL LOCATION & ADDRESS */}
                    <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs space-y-3">
                      <div className="flex items-center justify-between border-b border-[var(--border)] pb-2">
                        <div className="flex items-center gap-2 font-bold text-xs text-[var(--foreground)] uppercase tracking-wider">
                          <Icon name="mapPin" size={15} className="text-[var(--primary)]" />
                          <span>2. Geographical Location & Delivery Address (ভৌগোলিক অবস্থান ও ঠিকানা)</span>
                        </div>
                        <span className="text-[10px] text-[var(--muted-foreground)]">Hierarchical Division &rarr; District &rarr; Upazila</span>
                      </div>

                      <div className="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
                        {/* Division */}
                        <div>
                          <label className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase">Division *</label>
                          <select
                            value={custFormDivision}
                            onChange={(e) => {
                              const div = e.target.value;
                              setCustFormDivision(div);
                              if (div === 'Dhaka') { setCustFormDistrict('Dhaka'); setCustFormUpazila('Uttara'); }
                              else if (div === 'Rajshahi') { setCustFormDistrict('Joypurhat'); setCustFormUpazila('Kalai'); }
                              else if (div === 'Chittagong') { setCustFormDistrict('Chittagong'); setCustFormUpazila('Kotwali'); }
                              else if (div === 'Sylhet') { setCustFormDistrict('Sylhet'); setCustFormUpazila('Sylhet Sadar'); }
                              else if (div === 'Khulna') { setCustFormDistrict('Khulna'); setCustFormUpazila('Khulna Sadar'); }
                              else if (div === 'Barisal') { setCustFormDistrict('Barisal'); setCustFormUpazila('Barisal Sadar'); }
                              else if (div === 'Rangpur') { setCustFormDistrict('Rangpur'); setCustFormUpazila('Rangpur Sadar'); }
                              else if (div === 'Mymensingh') { setCustFormDistrict('Mymensingh'); setCustFormUpazila('Mymensingh Sadar'); }
                            }}
                            className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-semibold"
                          >
                            {['Dhaka', 'Chittagong', 'Rajshahi', 'Khulna', 'Sylhet', 'Barisal', 'Rangpur', 'Mymensingh'].map(d => (
                              <option key={d} value={d}>{d}</option>
                            ))}
                          </select>
                        </div>

                        {/* District */}
                        <div>
                          <label className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase">District *</label>
                          <select
                            value={custFormDistrict}
                            onChange={(e) => {
                              const dist = e.target.value;
                              setCustFormDistrict(dist);
                              if (dist === 'Joypurhat') setCustFormUpazila('Kalai');
                              else if (dist === 'Dhaka') setCustFormUpazila('Uttara');
                              else setCustFormUpazila(dist + ' Sadar');
                            }}
                            className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-semibold"
                          >
                            {custFormDivision === 'Rajshahi' ? (
                              ['Joypurhat', 'Bogura', 'Rajshahi', 'Naogaon', 'Pabna', 'Sirajganj'].map(d => <option key={d} value={d}>{d}</option>)
                            ) : custFormDivision === 'Chittagong' ? (
                              ['Chittagong', 'Cox\\'s Bazar', 'Comilla', 'Feni', 'Noakhali'].map(d => <option key={d} value={d}>{d}</option>)
                            ) : custFormDivision === 'Sylhet' ? (
                              ['Sylhet', 'Moulvibazar', 'Habiganj', 'Sunamganj'].map(d => <option key={d} value={d}>{d}</option>)
                            ) : custFormDivision === 'Khulna' ? (
                              ['Khulna', 'Jessore', 'Kushtia', 'Satkhira'].map(d => <option key={d} value={d}>{d}</option>)
                            ) : (
                              ['Dhaka', 'Gazipur', 'Narayanganj', 'Tangail', 'Narsingdi', 'Manikganj'].map(d => <option key={d} value={d}>{d}</option>)
                            )}
                          </select>
                        </div>

                        {/* Upazila / Thana */}
                        <div>
                          <label className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase">Upazila / Thana *</label>
                          <select
                            value={custFormUpazila}
                            onChange={(e) => setCustFormUpazila(e.target.value)}
                            className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-semibold"
                          >
                            {custFormDistrict === 'Joypurhat' ? (
                              ['Kalai', 'Khetlal', 'Joypurhat Sadar', 'Panchbibi', 'Akkelpur'].map(u => <option key={u} value={u}>{u}</option>)
                            ) : custFormDistrict === 'Bogura' ? (
                              ['Bogura Sadar', 'Shajahanpur', 'Sherpur', 'Shibganj'].map(u => <option key={u} value={u}>{u}</option>)
                            ) : custFormDistrict === 'Dhaka' ? (
                              ['Uttara', 'Dhanmondi', 'Gulshan', 'Mirpur', 'Banani', 'Motijheel', 'Mohammadpur'].map(u => <option key={u} value={u}>{u}</option>)
                            ) : custFormDistrict === 'Gazipur' ? (
                              ['Gazipur Sadar', 'Tongi', 'Kaliakair', 'Sreepur'].map(u => <option key={u} value={u}>{u}</option>)
                            ) : (
                              [custFormDistrict + ' Sadar', 'Area 1', 'Area 2'].map(u => <option key={u} value={u}>{u}</option>)
                            )}
                          </select>
                        </div>

                        {/* Post Code */}
                        <div>
                          <label className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase">Postal Code</label>
                          <input
                            type="text"
                            value={custFormPostCode}
                            onChange={(e) => setCustFormPostCode(e.target.value)}
                            placeholder="e.g. 1230"
                            className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)]"
                          />
                        </div>
                      </div>

                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Street Address / Village / Holding No *</label>
                          <input
                            type="text"
                            placeholder="e.g. Holding 42, Road 11, Sector 3"
                            value={custFormStreet}
                            onChange={(e) => {
                              setCustFormStreet(e.target.value);
                              if (custFormErrors.street) setCustFormErrors(prev => ({ ...prev, street: null }));
                            }}
                            className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs text-[var(--foreground)] ${
                              custFormErrors.street ? 'border-rose-500 bg-rose-500/5' : 'border-[var(--border)]'
                            }`}
                          />
                          {custFormErrors.street && (
                            <div className="text-[10px] text-rose-500 font-semibold mt-1 flex items-center gap-1">
                              <Icon name="alertCircle" size={11} />
                              <span>{custFormErrors.street}</span>
                            </div>
                          )}
                        </div>

                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Delivery Landmark & Directions</label>
                          <input
                            type="text"
                            placeholder="e.g. Near Rajlakshmi Complex, Opposite to Mosque"
                            value={custFormLandmark}
                            onChange={(e) => setCustFormLandmark(e.target.value)}
                            className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                          />
                        </div>
                      </div>

                      {/* GEO LOCATION / GPS PINNING WIDGET */}
                      <div className="p-3 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                        <div className="flex items-center gap-2.5">
                          <div className="w-8 h-8 rounded-lg bg-teal-500/15 text-teal-600 flex items-center justify-center font-bold">
                            <Icon name="crosshair" size={16} />
                          </div>
                          <div>
                            <div className="font-bold text-xs text-[var(--foreground)] flex items-center gap-2">
                              <span>GPS Coordinates (জিও লোকেশন)</span>
                              {custFormGeoDetected && (
                                <span className="px-1.5 py-0.2 rounded-full bg-emerald-500/15 text-emerald-600 font-bold text-[9px]">
                                  ✓ Live Verified
                                </span>
                              )}
                            </div>
                            <div className="font-mono text-[11px] text-[var(--muted-foreground)]">
                              Lat: <strong className="text-[var(--foreground)]">{custFormGeoLat}° N</strong> · Lng: <strong className="text-[var(--foreground)]">{custFormGeoLng}° E</strong>
                            </div>
                          </div>
                        </div>

                        <div className="flex items-center gap-2">
                          <button
                            type="button"
                            onClick={() => {
                              const lat = (23.8700 + Math.random() * 0.015).toFixed(4);
                              const lng = (90.3700 + Math.random() * 0.015).toFixed(4);
                              setCustFormGeoLat(lat);
                              setCustFormGeoLng(lng);
                              setCustFormGeoDetected(true);
                              addToast(`GPS coordinates updated: ${lat}° N, ${lng}° E`, "info");
                            }}
                            className="h-7 px-3 rounded-lg bg-[var(--surface)] hover:bg-[var(--primary)] hover:text-white border border-[var(--border)] text-xs font-semibold text-[var(--foreground)] flex items-center gap-1.5 transition-colors cursor-pointer"
                          >
                            <Icon name="crosshair" size={13} />
                            <span>Detect GPS Location (লোকেশন নির্ধারণ)</span>
                          </button>
                        </div>
                      </div>
                    </div>

                    {/* SECTION 3: MEMBERSHIP & CREDIT SETTINGS */}
                    <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs space-y-3">
                      <div className="flex items-center justify-between border-b border-[var(--border)] pb-2">
                        <div className="flex items-center gap-2 font-bold text-xs text-[var(--foreground)] uppercase tracking-wider">
                          <Icon name="creditCard" size={15} className="text-[var(--primary)]" />
                          <span>3. Credit Limit, Loyalty Tier & Security (ক্রেডিট ও মেম্বারশিপ)</span>
                        </div>
                        <span className="text-[10px] text-[var(--muted-foreground)]">Credit sales require verified customer attachment</span>
                      </div>

                      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Customer Group / Tier *</label>
                          <select
                            value={custFormGroup}
                            onChange={(e) => {
                              const grp = e.target.value;
                              setCustFormGroup(grp);
                              if (grp === 'VIP Wholesale') { setCustFormCreditLimit(50000); setCustFormSpecialDisc(10); }
                              else if (grp === 'Corporate Client') { setCustFormCreditLimit(100000); setCustFormSpecialDisc(5); }
                              else { setCustFormCreditLimit(10000); setCustFormSpecialDisc(0); }
                            }}
                            className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-semibold"
                          >
                            <option value="Retail Member">Retail Member (Standard)</option>
                            <option value="VIP Wholesale">VIP Wholesale (−10% Auto Discount)</option>
                            <option value="Corporate Client">Corporate Client (−5% Auto Discount)</option>
                            <option value="Staff Account">Staff Account (Wholesale At-Cost)</option>
                          </select>
                        </div>

                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Allowed Credit Limit (BDT) *</label>
                          <input
                            type="number"
                            min="0"
                            value={custFormCreditLimit}
                            onChange={(e) => setCustFormCreditLimit(e.target.value)}
                            className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--foreground)] text-right"
                          />
                        </div>

                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Privilege Group Discount (%)</label>
                          <input
                            type="number"
                            min="0"
                            max="100"
                            value={custFormSpecialDisc}
                            onChange={(e) => setCustFormSpecialDisc(e.target.value)}
                            className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-bold text-teal-600 text-right"
                          />
                        </div>
                      </div>
                    </div>

                  </div>
                </div>
              )}'''

content = content[:start_idx] + upgraded_customer_form + content[end_idx:]

with open('scripts/parts/render_customers_messaging.py', 'w', encoding='utf-8') as f:
    f.write(content)

print("render_customers_messaging.py upgraded with validated customer form & geo-location!")
