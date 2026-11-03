# scripts/parts/render_shell.py

RENDER_SHELL_TOP_JS = """
      // canAccess is master permission checker from app_state.py

      if (!isAuthenticated || currentScreen === 'login') {
        return (
          <div className="h-full w-full flex items-center justify-center bg-[var(--background)] p-4 select-none relative overflow-y-auto">
            <div className="w-full max-w-md bg-[var(--surface)] border border-[var(--border)] rounded-2xl shadow-2xl p-6 space-y-5 animate-in fade-in duration-200">
              {/* Brand Header */}
              <div className="text-center space-y-1.5">
                <div className="w-12 h-12 rounded-xl bg-[var(--primary)] text-[var(--primary-foreground)] mx-auto flex items-center justify-center font-bold text-lg shadow-md">
                  DF
                </div>
                <h1 className="text-xl font-bold tracking-tight text-[var(--foreground)]">Different Fashion</h1>
                <p className="text-xs text-[var(--muted-foreground)]">Point of Sale & Enterprise Retail Management</p>
              </div>

              {/* STEP 1: CREDENTIALS */}
              {loginStep === 'credentials' && (
                <form onSubmit={handleLoginSubmit} className="space-y-4">
                  {loginIsLockedOut && (
                    <div className="p-3 rounded-xl bg-[var(--danger-soft)] text-[var(--danger)] text-xs font-semibold flex items-start gap-2 border border-[var(--danger)]/30">
                      <Icon name="lock" size={16} className="shrink-0 mt-0.5" />
                      <div>
                        <div>Terminal Locked Out!</div>
                        <div className="text-[11px] font-normal mt-0.5">5 failed attempts exceeded. Contact Administrator to unlock this terminal.</div>
                        <button
                          type="button"
                          onClick={() => { setLoginIsLockedOut(false); setLoginAttempts(0); addToast("Terminal unlocked (Demo Reset)", "info"); }}
                          className="mt-1.5 text-[10px] underline font-bold"
                        >
                          Unlock Terminal (Demo Reset)
                        </button>
                      </div>
                    </div>
                  )}

                  <div>
                    <label className="text-xs font-semibold text-[var(--foreground)]">Username or Phone</label>
                    <div className="relative mt-1">
                      <input
                        type="text"
                        value={loginUsername}
                        onChange={(e) => setLoginUsername(e.target.value)}
                        placeholder="e.g. sumi@differentfashion.bd or 01711..."
                        className="w-full h-9 pl-8 pr-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] focus:border-[var(--primary)]"
                        disabled={loginIsLockedOut}
                      />
                      <Icon name="user" size={14} className="absolute left-2.5 top-2.5 text-[var(--muted-foreground)]" />
                    </div>
                  </div>

                  <div>
                    <div className="flex items-center justify-between">
                      <label className="text-xs font-semibold text-[var(--foreground)]">Password</label>
                      <button
                        type="button"
                        onClick={() => setLoginStep('forgot_password')}
                        className="text-[11px] text-[var(--primary)] hover:underline"
                      >
                        Forgot password?
                      </button>
                    </div>
                    <div className="relative mt-1">
                      <input
                        type={loginShowPassword ? "text" : "password"}
                        value={loginPassword}
                        onChange={(e) => setLoginPassword(e.target.value)}
                        placeholder="Enter password"
                        className="w-full h-9 pl-8 pr-9 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] focus:border-[var(--primary)]"
                        disabled={loginIsLockedOut}
                      />
                      <Icon name="lock" size={14} className="absolute left-2.5 top-2.5 text-[var(--muted-foreground)]" />
                      <button
                        type="button"
                        onClick={() => setLoginShowPassword(prev => !prev)}
                        className="absolute right-2.5 top-2.5 text-[var(--muted-foreground)] hover:text-[var(--foreground)]"
                        title={loginShowPassword ? "Hide Password" : "Show Password"}
                      >
                        <Icon name={loginShowPassword ? "eye" : "eye"} size={14} />
                      </button>
                    </div>
                  </div>

                  <div className="flex items-center justify-between text-xs">
                    <label className="flex items-center gap-2 cursor-pointer">
                      <input
                        type="checkbox"
                        checked={loginRememberDevice}
                        onChange={(e) => setLoginRememberDevice(e.target.checked)}
                        className="rounded border-[var(--border)] text-[var(--primary)]"
                      />
                      <span className="text-[var(--muted-foreground)] text-[11px]">Remember this terminal device</span>
                    </label>
                    {loginAttempts > 0 && !loginIsLockedOut && (
                      <span className="text-[10px] text-[var(--danger)] font-medium">Failed: {loginAttempts}/5 tries</span>
                    )}
                  </div>

                  <button
                    type="submit"
                    disabled={loginIsLockedOut}
                    className="w-full h-9 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-bold hover:bg-[var(--primary-hover)] transition-colors shadow-xs disabled:opacity-50"
                  >
                    Sign In to Terminal
                  </button>

                  {/* Fast Demo Role Login Chips */}
                  <div className="pt-3 border-t border-[var(--border)] space-y-1.5">
                    <div className="text-[10px] font-bold text-[var(--muted-foreground)] uppercase tracking-wider text-center">Fast Demo Sign In</div>
                    <div className="grid grid-cols-2 gap-1.5 text-[11px]">
                      {[
                        { name: 'Sumi', role: 'Cashier', pin: '1234', color: 'text-[var(--success)]' },
                        { name: 'Rafiq', role: 'Stock Keeper', pin: '2345', color: 'text-[var(--info)]' },
                        { name: 'Nusrat', role: 'Accountant', pin: '3456', color: 'text-[var(--primary)]' },
                        { name: 'Farhan', role: 'Manager', pin: '5678', color: 'text-[var(--warning)]' },
                        { name: 'Tanvir Ahmed', role: 'Owner', pin: '9999', color: 'text-[var(--danger)]' }
                      ].map(usr => (
                        <button
                          key={usr.role}
                          type="button"
                          onClick={() => {
                            setCurrentUser(prev => ({ ...prev, name: usr.name, role: usr.role, pin: usr.pin }));
                            setViewAsRole(usr.role);
                            setLoginUsername(`${usr.name.toLowerCase()}@differentfashion.bd`);
                            setLoginPassword(usr.pin);
                            setLoginAttempts(0);
                            setLoginIsLockedOut(false);
                            setLoginStep('branch_picker');
                            addToast(`Selected demo user: ${usr.name} (${usr.role})`, "info");
                          }}
                          className="p-1.5 rounded-lg bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-left flex items-center justify-between"
                        >
                          <span className="font-semibold truncate">{usr.name}</span>
                          <span className={`text-[10px] font-bold ${usr.color}`}>{usr.role.split(' ')[0]}</span>
                        </button>
                      ))}
                    </div>
                  </div>
                </form>
              )}

              {/* STEP 2: BRANCH PICKER */}
              {loginStep === 'branch_picker' && (
                <div className="space-y-4">
                  <div className="text-center">
                    <h2 className="text-sm font-bold text-[var(--foreground)]">Select Store Branch</h2>
                    <p className="text-[11px] text-[var(--muted-foreground)]">Choose register counter location for this session</p>
                  </div>

                  <div className="space-y-2">
                    {[
                      { id: 'df1', name: 'Different Fashion - Main [DF1]', address: 'Holding 42, Sector 3, Uttara Showroom', status: 'Counter 1 Active' },
                      { id: 'df2', name: 'Different Fashion - Dhanmondi [DF2]', address: 'Road 27, Dhanmondi Showroom', status: 'Counter 1 Active' },
                      { id: 'df3', name: 'Different Fashion - Uttara [DF3]', address: 'Sector 11, Uttara Showroom', status: 'Counter 2 Ready' }
                    ].map(br => (
                      <button
                        key={br.id}
                        type="button"
                        onClick={() => handleBranchSelect(br.name)}
                        className={`w-full p-3 rounded-xl border text-left flex items-center justify-between transition-all group ${
                          branch === br.name ? 'border-[var(--primary)] bg-[var(--primary-soft)]/20 shadow-xs' : 'border-[var(--border)] hover:border-[var(--primary)] bg-[var(--surface-sunken)]'
                        }`}
                      >
                        <div>
                          <div className="font-bold text-xs text-[var(--foreground)] group-hover:text-[var(--primary)]">{br.name}</div>
                          <div className="text-[10px] text-[var(--muted-foreground)]">{br.address}</div>
                        </div>
                        <div className="text-right">
                          <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-[var(--success-soft)] text-[var(--success)]">
                            {br.status}
                          </span>
                        </div>
                      </button>
                    ))}
                  </div>

                  <button
                    type="button"
                    onClick={() => setLoginStep('credentials')}
                    className="w-full text-center text-xs text-[var(--muted-foreground)] hover:text-[var(--foreground)] pt-2"
                  >
                    ← Back to credentials
                  </button>
                </div>
              )}

              {/* STEP 3: FORGOT PASSWORD */}
              {loginStep === 'forgot_password' && (
                <div className="space-y-4">
                  <div className="text-center">
                    <h2 className="text-sm font-bold text-[var(--foreground)]">Reset Password</h2>
                    <p className="text-[11px] text-[var(--muted-foreground)]">Recover staff account via SMS OTP code</p>
                  </div>

                  {forgotStep === 'input' && (
                    <div className="space-y-3">
                      <div>
                        <label className="text-xs font-semibold text-[var(--foreground)]">Registered Mobile or Email</label>
                        <input
                          type="text"
                          value={forgotContact}
                          onChange={(e) => setForgotContact(e.target.value)}
                          placeholder="e.g. 01711223344"
                          className="w-full mt-1 h-9 px-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                        />
                      </div>
                      <button
                        type="button"
                        onClick={() => {
                          if (!forgotContact.trim()) { addToast("Enter mobile or email!", "warning"); return; }
                          setForgotStep('otp');
                          addToast("Verification OTP sent to " + forgotContact + " (Code: 1234)", "info");
                        }}
                        className="w-full h-9 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-bold"
                      >
                        Send Reset OTP Code
                      </button>
                    </div>
                  )}

                  {forgotStep === 'otp' && (
                    <div className="space-y-3">
                      <div className="p-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-center">
                        <span className="text-[var(--muted-foreground)]">OTP sent to <strong>{forgotContact}</strong></span>
                        <div className="text-[10px] text-[var(--primary)] font-semibold mt-0.5">Demo hint: Enter 1234</div>
                      </div>
                      <div>
                        <label className="text-xs font-semibold text-[var(--foreground)]">4-Digit OTP Code</label>
                        <input
                          type="text"
                          maxLength={4}
                          value={forgotOtp}
                          onChange={(e) => setForgotOtp(e.target.value)}
                          placeholder="1234"
                          className="w-full mt-1 h-9 px-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono text-center tracking-widest text-lg"
                        />
                      </div>
                      <div>
                        <label className="text-xs font-semibold text-[var(--foreground)]">New Password</label>
                        <input
                          type="password"
                          value={forgotNewPass}
                          onChange={(e) => setForgotNewPass(e.target.value)}
                          placeholder="Enter new password"
                          className="w-full mt-1 h-9 px-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs"
                        />
                      </div>
                      <button
                        type="button"
                        onClick={() => {
                          if (forgotOtp !== '1234') { addToast("Invalid OTP code! Use 1234", "danger"); return; }
                          if (!forgotNewPass || forgotNewPass.length < 4) { addToast("Password must be at least 4 chars!", "warning"); return; }
                          setLoginPassword(forgotNewPass);
                          setForgotStep('input');
                          setLoginStep('credentials');
                          addToast("Password reset successfully! Please sign in.", "success");
                        }}
                        className="w-full h-9 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-bold"
                      >
                        Save New Password & Sign In
                      </button>
                    </div>
                  )}

                  <button
                    type="button"
                    onClick={() => { setLoginStep('credentials'); setForgotStep('input'); }}
                    className="w-full text-center text-xs text-[var(--muted-foreground)] hover:text-[var(--foreground)] pt-2"
                  >
                    ← Back to Sign In
                  </button>
                </div>
              )}
            </div>

            {/* Floating Toast Notification Container */}
            <div className="fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm pointer-events-none">
              {toasts.map(t => (
                <div key={t.id} className={`px-3 py-2 rounded-lg text-xs font-medium shadow-lg pointer-events-auto flex items-center gap-2 ${
                  t.type === 'danger' ? 'bg-[var(--danger)] text-white' : t.type === 'success' ? 'bg-[var(--success)] text-white' : 'bg-[var(--surface-raised)] text-[var(--foreground)] border border-[var(--border)]'
                }`}>
                  <span>{t.message}</span>
                </div>
              ))}
            </div>
          </div>
        );
      }

      return (
        <div className="h-full w-full flex flex-col overflow-hidden bg-[var(--background)] text-[var(--foreground)]" onContextMenu={(e) => {
          if (!e.defaultPrevented) {
            openContextMenu(e, 'Quick Navigation', [
              { label: 'POS Register', shortcut: 'F1', action: () => { setPosMode('sale'); setCurrentScreen('pos'); } },
              { label: 'Return / Exchange Mode', shortcut: 'F11', action: () => { startReturnModeForInvoice('DF1-R1-26000412'); setCurrentScreen('pos'); } },
              { label: 'Collect Customer Due', shortcut: 'Ctrl+D', action: () => openCollectDueModal() },
              { label: 'Sales History', shortcut: 'G S', action: () => navigateToScreen('sales') },
              { label: 'Customer Directory', shortcut: 'G C', action: () => navigateToScreen('customers') },
              { label: 'Toggle Density', action: () => setDensity(d => d === 'comfortable' ? 'compact' : 'comfortable') },
              { type: 'separator' },
              { label: 'Lock Register Terminal', shortcut: 'Ctrl+L', action: () => setIsLocked(true), destructive: true }
            ]);
          }
        }}>

          {/* ================= 1. TOP BAR (52px) ================= */}
          <header className="h-[52px] bg-[var(--surface)] border-b border-[var(--border)] px-3 flex items-center justify-between shrink-0 select-none z-30">
            {/* Left Section: Logo, Tenant & Branch */}
            <div className="flex items-center gap-3">
              <button 
                onClick={() => setSidebarCollapsed(prev => !prev)}
                className="p-1.5 rounded hover:bg-[var(--surface-sunken)] text-[var(--muted-foreground)] focus-visible:ring-1"
                title="Toggle Sidebar (Ctrl+B)"
              >
                <div className="w-4 h-4 flex flex-col justify-between">
                  <span className="h-0.5 w-full bg-current rounded"></span>
                  <span className="h-0.5 w-full bg-current rounded"></span>
                  <span className="h-0.5 w-full bg-current rounded"></span>
                </div>
              </button>

              <div className="flex items-center gap-2">
                <div className="w-7 h-7 rounded-md bg-[var(--primary)] text-[var(--primary-foreground)] flex items-center justify-center font-bold text-xs shadow-xs">
                  DF
                </div>
                <div>
                  <div className="flex items-center gap-1.5">
                    <span className="font-bold text-xs tracking-wider uppercase text-[var(--foreground)]">Different Fashion</span>
                    <span className="text-[10px] px-1 py-0.2 rounded bg-[var(--surface-sunken)] text-[var(--muted-foreground)] font-medium">Retail POS</span>
                  </div>
                  {/* Branch switcher */}
                  <div className="flex items-center gap-1 text-[11px] text-[var(--muted-foreground)] hover:text-[var(--primary)] cursor-pointer group"
                    onClick={() => setBranch(b => b.includes('Main') ? 'Different Fashion - 2 [DF2]' : 'Different Fashion - Main [DF1]')}
                    title="Switch Branch"
                  >
                    <span className="truncate max-w-[160px] font-medium">{branch}</span>
                    <Icon name="chevronDown" size={12} className="group-hover:translate-y-0.5 transition-transform" />
                  </div>
                </div>
              </div>

              {/* Direct Role Switcher in Top Bar */}
              <div className="hidden lg:flex items-center gap-1 bg-[var(--surface-sunken)] border border-[var(--border)] px-2 py-0.5 rounded-lg text-xs ml-2">
                <span className="text-[10px] text-[var(--muted-foreground)] font-semibold uppercase">Role:</span>
                <select
                  value={viewAsRole}
                  onChange={(e) => {
                    const newRole = e.target.value;
                    setViewAsRole(newRole);
                    const demoUsers = {
                      'Cashier': { name: 'Sumi', role: 'Cashier', pin: '1234' },
                      'Stock Keeper': { name: 'Rafiq', role: 'Stock Keeper', pin: '2345' },
                      'Accountant': { name: 'Nusrat', role: 'Accountant', pin: '3456' },
                      'Manager': { name: 'Farhan', role: 'Manager', pin: '5678' },
                      'Owner': { name: 'Tanvir Ahmed', role: 'Owner', pin: '9999' },
                      'Super Admin': { name: 'Super Admin', role: 'Super Admin', pin: '9999' }
                    };
                    if (demoUsers[newRole]) setCurrentUser(demoUsers[newRole]);
                    addToast(`Switched view to role: ${newRole}`, "info");
                  }}
                  className="bg-transparent font-bold text-xs text-[var(--foreground)] cursor-pointer focus:outline-none"
                >
                  <option value="Owner">👑 Owner (All)</option>
                  <option value="Manager">👔 Manager</option>
                  <option value="Cashier">🛒 Cashier</option>
                  <option value="Accountant">📊 Accountant</option>
                  <option value="Stock Keeper">📦 Stock Keeper</option>
                  <option value="Super Admin">⚡ Super Admin</option>
                </select>
              </div>
            </div>

            {/* Middle Section: Command Palette Trigger Input */}
            <div className="hidden md:flex items-center flex-1 max-w-md mx-4">
              <button
                onClick={() => setShowCommandPalette(true)}
                className="w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] hover:border-[var(--primary)] text-[var(--muted-foreground)] text-xs flex items-center justify-between transition-colors group cursor-text"
              >
                <div className="flex items-center gap-2">
                  <Icon name="search" size={14} className="group-hover:text-[var(--primary)]" />
                  <span>{lang === 'bn' ? 'অনুসন্ধান বা কমান্ড... (Ctrl K)' : 'Search anything or jump to... (Ctrl K)'}</span>
                </div>
                <div className="flex items-center gap-1">
                  <kbd className="text-[10px] px-1.5 py-0.5 bg-[var(--surface)] border border-[var(--border)] rounded text-[var(--muted-foreground)] font-mono">Ctrl K</kbd>
                </div>
              </button>
            </div>

            {/* Right Section: Sync Pill, TODAY PILL, Alerts, Clock, Lang, Theme, User */}
            <div className="flex items-center gap-2">
              {/* Sync Pill */}
              <div className="relative">
                <button
                  onClick={() => setShowSyncCenter(prev => !prev)}
                  className={`h-7 px-2 rounded-full border text-[11px] font-medium flex items-center gap-1.5 transition-colors ${currentSyncStatus.colorClass}`}
                  title="Click to view Sync Center"
                >
                  <Icon name={currentSyncStatus.icon} size={12} className={isSyncing ? "animate-spin" : ""} />
                  <span>{currentSyncStatus.label}</span>
                </button>

                {/* Sync Center Popover */}
                {showSyncCenter && (
                  <>
                    <div className="fixed inset-0 z-40" onClick={() => setShowSyncCenter(false)} />
                    <div className="absolute right-0 top-9 w-72 bg-[var(--surface-raised)] border border-[var(--border)] rounded-xl shadow-xl p-3 z-50 text-xs">
                      <div className="flex items-center justify-between pb-2 border-b border-[var(--border)] font-semibold">
                        <div className="flex items-center gap-1.5">
                          <Icon name="database" size={14} className="text-[var(--primary)]" />
                          <span>Offline Sync Center</span>
                        </div>
                        <span className="text-[10px] text-[var(--muted-foreground)]">Local SQLite: 12.4 MB</span>
                      </div>
                      <div className="py-2.5 space-y-1.5">
                        <div className="flex justify-between text-[11px]">
                          <span className="text-[var(--muted-foreground)]">Status:</span>
                          <span className="font-semibold">{currentSyncStatus.label}</span>
                        </div>
                        <div className="flex justify-between text-[11px]">
                          <span className="text-[var(--muted-foreground)]">Queued Offline Sales:</span>
                          <span className="font-mono font-semibold">{isOfflineMode ? '3 sales' : '0 pending'}</span>
                        </div>
                        <div className="flex justify-between text-[11px]">
                          <span className="text-[var(--muted-foreground)]">Last Successful Sync:</span>
                          <span>Today, 10:41 AM</span>
                        </div>
                      </div>
                      <div className="pt-2 border-t border-[var(--border)] flex gap-2">
                        <button
                          onClick={handleSyncRetry}
                          disabled={isSyncing}
                          className="flex-1 h-7 rounded bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-medium flex items-center justify-center gap-1 hover:bg-[var(--primary-hover)] disabled:opacity-50"
                        >
                          <Icon name="refresh" size={12} className={isSyncing ? "animate-spin" : ""} />
                          <span>{isSyncing ? 'Syncing...' : 'Retry Sync Now'}</span>
                        </button>
                        <button
                          onClick={() => {
                            setSyncStateIndex((syncStateIndex + 1) % 4);
                            addToast(`Simulated network state: ${syncStatuses[(syncStateIndex + 1) % 4].label}`, "info");
                          }}
                          className="px-2 h-7 rounded bg-[var(--surface-sunken)] border border-[var(--border)] hover:bg-[var(--border)] text-[10px]"
                          title="Cycle state: Online -> Offline -> Unsynced -> Error"
                        >
                          Cycle
                        </button>
                      </div>
                    </div>
                  </>
                )}
              </div>

              {/* REPLACED REGISTER/SHIFT PILL WITH "TODAY" PILL */}
              <div className="relative">
                <button
                  onClick={() => setShowTodaySummaryPopover(prev => !prev)}
                  className="hidden sm:flex items-center gap-1.5 h-7 px-2.5 rounded-full bg-[var(--surface-sunken)] border border-[var(--border)] text-[11px] text-[var(--foreground)] hover:border-[var(--primary)] cursor-pointer transition-colors"
                  title="Click to view Today's Store Summary"
                >
                  <span className="w-2 h-2 rounded-full bg-[var(--success)]"></span>
                  <span className="font-bold">Today {formatMoney(todaySummary.gross_sales)}</span>
                  <span className="text-[var(--muted-foreground)] font-medium">· {todaySummary.sales_count} sales</span>
                  <Icon name="chevronDown" size={11} className="text-[var(--muted-foreground)]" />
                </button>

                {/* TODAY SUMMARY POPOVER */}
                {showTodaySummaryPopover && (
                  <>
                    <div className="fixed inset-0 z-40" onClick={() => setShowTodaySummaryPopover(false)} />
                    <div className="absolute right-0 top-9 w-80 bg-[var(--surface-raised)] border border-[var(--border)] rounded-2xl shadow-2xl p-4 z-50 text-xs space-y-3 animate-in fade-in zoom-in-95 duration-100">
                      <div className="flex items-center justify-between pb-2 border-b border-[var(--border)]">
                        <div className="font-bold flex items-center gap-1.5">
                          <Icon name="calendar" size={15} className="text-[var(--primary)]" />
                          <span>Today's Business Summary</span>
                        </div>
                        <span className="text-[10px] text-[var(--muted-foreground)]">Live Day Summary</span>
                      </div>

                      <div className="space-y-1.5 text-xs">
                        <div className="flex justify-between">
                          <span className="text-[var(--muted-foreground)]">Gross Sales ({todaySummary.sales_count} bills):</span>
                          <span className="font-bold num">{formatMoney(todaySummary.gross_sales)}</span>
                        </div>
                        <div className="flex justify-between text-[var(--danger)]">
                          <span>Returns ({todaySummary.returns_count} returns):</span>
                          <span className="num">−{formatMoney(todaySummary.returns_total)}</span>
                        </div>
                        <div className="flex justify-between text-[var(--danger)]">
                          <span>Discounts Given:</span>
                          <span className="num">−{formatMoney(todaySummary.discounts_total)}</span>
                        </div>
                        <div className="pt-1 border-t border-[var(--border)] flex justify-between font-bold text-sm">
                          <span>Net Sales:</span>
                          <span className="text-[var(--primary)] num">{formatMoney(todaySummary.net_sales)}</span>
                        </div>

                        <div className="pt-2 border-t border-[var(--border)] space-y-1 text-[11px]">
                          <div className="font-bold text-[var(--muted-foreground)] uppercase text-[10px] tracking-wider">Tender Collections</div>
                          <div className="flex justify-between"><span>Cash in Drawer:</span><span className="font-medium num">{formatMoney(todaySummary.cash_total)}</span></div>
                          <div className="flex justify-between"><span>bKash Merchant:</span><span className="font-medium num">{formatMoney(todaySummary.bkash_total)}</span></div>
                          <div className="flex justify-between"><span>POS Cards:</span><span className="font-medium num">{formatMoney(todaySummary.card_total)}</span></div>
                        </div>

                        <div className="pt-2 border-t border-[var(--border)] space-y-1 text-[11px]">
                          <div className="font-bold text-[var(--muted-foreground)] uppercase text-[10px] tracking-wider">Customer Dues</div>
                          <div className="flex justify-between text-[var(--warning)]"><span>New Dues Created:</span><span className="font-semibold num">+{formatMoney(todaySummary.dues_created)}</span></div>
                          <div className="flex justify-between text-[var(--success)]"><span>Old Dues Collected:</span><span className="font-semibold num">{formatMoney(todaySummary.dues_collected)}</span></div>
                        </div>
                      </div>

                      <div className="pt-2 border-t border-[var(--border)] flex gap-2">
                        <button
                          onClick={() => {
                            setShowTodaySummaryPopover(false);
                            setShowDaySummaryPrintModal(true);
                          }}
                          className="w-full h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold flex items-center justify-center gap-1.5 shadow-xs hover:bg-[var(--primary-hover)]"
                        >
                          <Icon name="printer" size={13} />
                          <span>Print Day Summary</span>
                        </button>
                      </div>
                    </div>
                  </>
                )}
              </div>

              {/* Notifications Bell */}
              <div className="relative">
                <button
                  onClick={() => setShowNotifications(prev => !prev)}
                  className="p-1.5 rounded-lg hover:bg-[var(--surface-sunken)] text-[var(--muted-foreground)] relative focus-visible:ring-1"
                  title="Notifications"
                >
                  <Icon name="bell" size={16} />
                  {notifications.some(n => n.unread) && (
                    <span className="absolute top-1 right-1 w-2 h-2 rounded-full bg-[var(--danger)]"></span>
                  )}
                </button>

                {showNotifications && (
                  <>
                    <div className="fixed inset-0 z-40" onClick={() => setShowNotifications(false)} />
                    <div className="absolute right-0 top-9 w-80 bg-[var(--surface-raised)] border border-[var(--border)] rounded-xl shadow-xl overflow-hidden z-50 text-xs">
                      <div className="p-2.5 border-b border-[var(--border)] flex items-center justify-between">
                        <div className="font-bold flex items-center gap-1.5">
                          <span>Notifications</span>
                          <span className="px-1.5 py-0.2 rounded-full bg-[var(--danger-soft)] text-[var(--danger)] text-[10px] font-mono font-bold">4</span>
                        </div>
                        <button 
                          onClick={() => {
                            setNotifications(prev => prev.map(n => ({ ...n, unread: false })));
                            addToast("All notifications marked as read", "info");
                          }}
                          className="text-[10px] text-[var(--primary)] hover:underline"
                        >
                          Mark all read
                        </button>
                      </div>
                      {/* Tabs */}
                      <div className="flex border-b border-[var(--border)] text-[11px] bg-[var(--surface-sunken)]">
                        {['all', 'alert', 'export'].map(tab => (
                          <button
                            key={tab}
                            onClick={() => setNotifTab(tab)}
                            className={`flex-1 py-1.5 font-medium capitalize text-center ${notifTab === tab ? 'text-[var(--primary)] border-b-2 border-[var(--primary)] bg-[var(--surface)]' : 'text-[var(--muted-foreground)]'}`}
                          >
                            {tab}
                          </button>
                        ))}
                      </div>
                      {/* List */}
                      <div className="max-h-72 overflow-y-auto divide-y divide-[var(--border)]">
                        {notifications.filter(n => notifTab === 'all' || n.type === notifTab).map(n => (
                          <div key={n.id} className={`p-2.5 hover:bg-[var(--surface-sunken)] transition-colors flex gap-2 ${n.unread ? 'bg-[var(--primary-soft)]/20' : ''}`}>
                            <div className="pt-0.5">
                              <span className={`w-2 h-2 rounded-full inline-block ${n.unread ? 'bg-[var(--primary)]' : 'bg-transparent'}`}></span>
                            </div>
                            <div className="flex-1">
                              <div className="text-[11px] font-medium text-[var(--foreground)] leading-snug">{n.title}</div>
                              <div className="text-[10px] text-[var(--muted-foreground)] mt-0.5">{n.time}</div>
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  </>
                )}
              </div>

              {/* Live Clock */}
              <div className="hidden xl:flex flex-col text-right leading-tight border-l border-[var(--border)] pl-2.5">
                <span className="font-mono text-xs font-semibold text-[var(--foreground)] num">
                  {currentTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                </span>
                <span className="text-[10px] text-[var(--muted-foreground)]">
                  {currentTime.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' })}
                </span>
              </div>

              {/* EN/বাং Toggle */}
              <button
                onClick={() => {
                  const nextLang = lang === 'en' ? 'bn' : 'en';
                  setLang(nextLang);
                  addToast(`Language switched to ${nextLang === 'bn' ? 'বাংলা' : 'English'}`, "info");
                }}
                className="h-7 px-2 rounded-md bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold hover:border-[var(--primary)] transition-colors"
                title="Toggle Language"
              >
                {lang === 'en' ? 'বাং' : 'EN'}
              </button>

              {/* Theme & Palette Button */}
              <div className="relative">
                <button
                  onClick={() => setShowThemePopover(prev => !prev)}
                  className="p-1.5 rounded-lg hover:bg-[var(--surface-sunken)] text-[var(--muted-foreground)] focus-visible:ring-1"
                  title="Theme & Scheme Options"
                >
                  <Icon name="palette" size={16} />
                </button>

                {showThemePopover && (
                  <>
                    <div className="fixed inset-0 z-40" onClick={() => setShowThemePopover(false)} />
                    <div className="absolute right-0 top-9 w-64 bg-[var(--surface-raised)] border border-[var(--border)] rounded-xl shadow-xl p-3 z-50 text-xs space-y-3">
                      <div className="font-semibold text-xs border-b border-[var(--border)] pb-1.5">Appearance</div>
                      
                      {/* Mode Toggle */}
                      <div>
                        <div className="text-[10px] text-[var(--muted-foreground)] mb-1">Color Mode</div>
                        <div className="grid grid-cols-2 gap-1 bg-[var(--surface-sunken)] p-1 rounded-lg">
                          <button
                            onClick={() => setTheme('light')}
                            className={`py-1 rounded text-center font-medium ${theme === 'light' ? 'bg-[var(--surface)] text-[var(--primary)] shadow-xs' : 'text-[var(--muted-foreground)]'}`}
                          >
                            Light
                          </button>
                          <button
                            onClick={() => setTheme('dark')}
                            className={`py-1 rounded text-center font-medium ${theme === 'dark' ? 'bg-[var(--surface)] text-[var(--primary)] shadow-xs' : 'text-[var(--muted-foreground)]'}`}
                          >
                            Dark
                          </button>
                        </div>
                      </div>

                      {/* Color Schemes */}
                      <div>
                        <div className="text-[10px] text-[var(--muted-foreground)] mb-1">Accent Scheme</div>
                        <div className="grid grid-cols-6 gap-1.5">
                          {[
                            { id: 'teal', color: '#0F766E' },
                            { id: 'indigo', color: '#4338CA' },
                            { id: 'violet', color: '#6D28D9' },
                            { id: 'rose', color: '#BE123C' },
                            { id: 'ocean', color: '#0369A1' },
                            { id: 'graphite', color: '#334155' },
                          ].map(s => (
                            <button
                              key={s.id}
                              onClick={() => setScheme(s.id)}
                              style={{ backgroundColor: s.color }}
                              className={`w-7 h-7 rounded-full flex items-center justify-center text-white transition-transform ${scheme === s.id ? 'ring-2 ring-offset-2 ring-[var(--ring)] scale-110' : 'hover:scale-105'}`}
                              title={s.id}
                            >
                              {scheme === s.id && <Icon name="check" size={12} />}
                            </button>
                          ))}
                        </div>
                      </div>

                      {/* Density Toggle */}
                      <div>
                        <div className="text-[10px] text-[var(--muted-foreground)] mb-1">Table Density</div>
                        <div className="grid grid-cols-2 gap-1 bg-[var(--surface-sunken)] p-1 rounded-lg">
                          <button
                            onClick={() => setDensity('comfortable')}
                            className={`py-1 rounded text-center font-medium ${density === 'comfortable' ? 'bg-[var(--surface)] text-[var(--primary)] shadow-xs' : 'text-[var(--muted-foreground)]'}`}
                          >
                            Comfortable (44px)
                          </button>
                          <button
                            onClick={() => setDensity('compact')}
                            className={`py-1 rounded text-center font-medium ${density === 'compact' ? 'bg-[var(--surface)] text-[var(--primary)] shadow-xs' : 'text-[var(--muted-foreground)]'}`}
                          >
                            Compact (32px)
                          </button>
                        </div>
                      </div>
                    </div>
                  </>
                )}
              </div>

              {/* User Avatar Menu */}
              <div className="relative">
                <button
                  onClick={() => setShowAvatarMenu(prev => !prev)}
                  className="flex items-center gap-1.5 p-1 rounded-lg hover:bg-[var(--surface-sunken)] focus-visible:ring-1"
                >
                  <div className="w-7 h-7 rounded-full bg-[var(--surface-sunken)] border border-[var(--border)] flex items-center justify-center font-bold text-xs text-[var(--foreground)]">
                    {currentUser.name[0]}
                  </div>
                  <div className="hidden sm:block text-left text-xs leading-tight">
                    <div className="font-semibold">{currentUser.name}</div>
                    <div className="text-[10px] text-[var(--muted-foreground)]">{currentUser.role}</div>
                  </div>
                  <Icon name="chevronDown" size={12} className="text-[var(--muted-foreground)]" />
                </button>

                {showAvatarMenu && (
                  <>
                    <div className="fixed inset-0 z-40" onClick={() => setShowAvatarMenu(false)} />
                    <div className="absolute right-0 top-9 w-56 bg-[var(--surface-raised)] border border-[var(--border)] rounded-xl shadow-xl py-1.5 z-50 text-xs divide-y divide-[var(--border)]">
                      <div className="px-3 py-2">
                        <div className="font-semibold text-[var(--foreground)]">{currentUser.name}</div>
                        <div className="text-[10px] text-[var(--muted-foreground)]">Role: {currentUser.role} · Max Disc: {currentRoleLimit}%</div>
                      </div>
                      {/* View As Role */}
                      <div className="p-2">
                        <div className="text-[10px] font-semibold text-[var(--muted-foreground)] mb-1 uppercase tracking-wider">Demo: View As Role</div>
                        <select 
                          value={viewAsRole}
                          onChange={(e) => {
                            setViewAsRole(e.target.value);
                            setCurrentUser(prev => ({ ...prev, role: e.target.value }));
                            addToast(`Switched view to role: ${e.target.value}`, "info");
                          }}
                          className="w-full text-xs p-1 rounded bg-[var(--surface-sunken)] border border-[var(--border)] text-[var(--foreground)]"
                        >
                          <option value="Super Admin">Super Admin</option>
                          <option value="Owner">Owner</option>
                          <option value="Manager">Manager</option>
                          <option value="Cashier">Cashier (Max 10% Disc)</option>
                          <option value="Accountant">Accountant</option>
                          <option value="Stock Keeper">Stock Keeper</option>
                        </select>
                      </div>
                      <div className="py-1">
                        <button 
                          onClick={() => { navigateToScreen('account'); setShowAvatarMenu(false); }}
                          className="w-full px-3 py-1.5 text-left hover:bg-[var(--surface-sunken)] flex items-center justify-between text-[var(--foreground)]"
                        >
                          <span className="flex items-center gap-2">
                            <Icon name="user" size={13} className="text-[var(--primary)]" />
                            <span>My Profile & Account</span>
                          </span>
                        </button>
                        <button 
                          onClick={() => { setShowSwitchUserPinModal(true); setShowAvatarMenu(false); }}
                          className="w-full px-3 py-1.5 text-left hover:bg-[var(--surface-sunken)] flex items-center justify-between text-[var(--foreground)]"
                        >
                          <span className="flex items-center gap-2">
                            <Icon name="key" size={13} className="text-[var(--warning)]" />
                            <span>Switch User (PIN)</span>
                          </span>
                        </button>
                        <button 
                          onClick={() => { setShowShortcutsModal(true); setShowAvatarMenu(false); }}
                          className="w-full px-3 py-1.5 text-left hover:bg-[var(--surface-sunken)] flex items-center justify-between"
                        >
                          <span>Keyboard Shortcuts</span>
                          <kbd className="text-[10px]">?</kbd>
                        </button>
                        <button 
                          onClick={() => { setIsLocked(true); setShowAvatarMenu(false); }}
                          className="w-full px-3 py-1.5 text-left hover:bg-[var(--surface-sunken)] flex items-center justify-between text-[var(--warning)]"
                        >
                          <span>Lock Screen</span>
                          <kbd className="text-[10px]">Ctrl+L</kbd>
                        </button>
                        <button 
                          onClick={() => { handleLogout(); setShowAvatarMenu(false); }}
                          className="w-full px-3 py-1.5 text-left hover:bg-[var(--danger-soft)] text-[var(--danger)] flex items-center justify-between font-semibold"
                        >
                          <span className="flex items-center gap-2">
                            <Icon name="logOut" size={13} />
                            <span>Log Out Terminal</span>
                          </span>
                        </button>
                      </div>
                    </div>
                  </>
                )}
              </div>
            </div>
          </header>

          {/* ================= BODY WRAPPER: SIDEBAR + MAIN SCREEN ================= */}
          <div className="flex-1 flex overflow-hidden">

            {/* ================= 2. DESKTOP SIDEBAR ================= */}
            <aside className={`bg-[var(--surface)] border-r border-[var(--border)] flex flex-col shrink-0 transition-all duration-200 select-none z-20 ${sidebarCollapsed ? 'w-14' : 'w-56'}`}>
              <div className="flex-1 overflow-y-auto py-2 space-y-3">
                {/* Active Role Switcher Card in Sidebar */}
                {!sidebarCollapsed && (
                  <div className="px-2">
                    <div className="p-2 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] text-xs space-y-1">
                      <div className="flex items-center justify-between text-[10px] font-bold text-[var(--muted-foreground)] uppercase tracking-wider">
                        <span>Active Role</span>
                        <span className="px-1.5 py-0.2 rounded font-bold bg-[var(--primary-soft)] text-[var(--primary)] text-[9px]">{viewAsRole}</span>
                      </div>
                      <select
                        value={viewAsRole}
                        onChange={(e) => {
                          const newRole = e.target.value;
                          setViewAsRole(newRole);
                          const demoUsers = {
                            'Cashier': { name: 'Sumi', role: 'Cashier', pin: '1234' },
                            'Stock Keeper': { name: 'Rafiq', role: 'Stock Keeper', pin: '2345' },
                            'Accountant': { name: 'Nusrat', role: 'Accountant', pin: '3456' },
                            'Manager': { name: 'Farhan', role: 'Manager', pin: '5678' },
                            'Owner': { name: 'Tanvir Ahmed', role: 'Owner', pin: '9999' },
                            'Super Admin': { name: 'Super Admin', role: 'Super Admin', pin: '9999' }
                          };
                          if (demoUsers[newRole]) setCurrentUser(demoUsers[newRole]);
                          addToast(`Switched view to role: ${newRole}`, "info");
                        }}
                        className="w-full text-xs font-semibold p-1 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-[var(--foreground)] cursor-pointer"
                      >
                        <option value="Owner">👑 Owner (All Menus)</option>
                        <option value="Manager">👔 Manager</option>
                        <option value="Cashier">🛒 Cashier (POS & Sales)</option>
                        <option value="Accountant">📊 Accountant (Ledgers)</option>
                        <option value="Stock Keeper">📦 Stock Keeper (Inventory)</option>
                        <option value="Super Admin">⚡ Super Admin</option>
                      </select>
                    </div>
                  </div>
                )}
                
                {/* Navigation Groups */}
                {[
                  {
                    group: 'SELL',
                    roleRequired: 'Cashier',
                    items: [
                      { id: 'pos', label: 'POS Register', label_bn: 'পয়েন্ট অব সেল', icon: 'shoppingCart', shortcut: 'F1' },
                      { id: 'sales', label: 'Sales History', label_bn: 'বিক্রয় বিবরণী', icon: 'fileText', shortcut: 'G S' },
                      { id: 'returns', label: 'Returns & Exchange', label_bn: 'ফেরত ও পরিবর্তন', icon: 'refresh', shortcut: 'G T' },
                      { id: 'due_collection', label: 'Due Collection', label_bn: 'বাকি আদায়', icon: 'banknote', shortcut: 'G U' }
                    ]
                  },
                  {
                    group: 'CUSTOMERS',
                    roleRequired: 'Cashier',
                    items: [
                      { id: 'customers', label: 'Customers', label_bn: 'গ্রাহক তালিকা', icon: 'user', shortcut: 'G C' },
                      { id: 'membership_cards', label: 'Membership Cards', label_bn: 'মেম্বারশিপ কার্ড', icon: 'idCard', shortcut: '' },
                      { id: 'messaging', label: 'WhatsApp & SMS', label_bn: 'বার্তা প্রেরণ', icon: 'send', shortcut: 'G W' }
                    ]
                  },
                  {
                    group: 'CATALOG & STOCK',
                    roleRequired: 'Stock Keeper',
                    items: [
                      { id: 'products', label: 'Products & SKUs', label_bn: 'পণ্য তালিকা', icon: 'tag', shortcut: 'G I' },
                      { id: 'stock', label: 'Stock & Transfer', label_bn: 'মজুত ও স্থানান্তর', icon: 'package', shortcut: 'G K' },
                      { id: 'purchases', label: 'Purchases (GRN)', label_bn: 'ক্রয় চালান', icon: 'truck', shortcut: 'G P' },
                      { id: 'vendors', label: 'Vendor Directory', label_bn: 'সরবরাহকারী', icon: 'users', shortcut: 'G V' }
                    ]
                  },
                  {
                    group: 'MONEY',
                    roleRequired: 'Accountant',
                    items: [
                      { id: 'money', label: 'Accounts & Expense', label_bn: 'হিসাব ও খরচ', icon: 'dollarSign', shortcut: 'G M' },
                      { id: 'fixed_assets', label: 'Fixed Assets', label_bn: 'স্থাবর সম্পত্তি', icon: 'layers', shortcut: '' }
                    ]
                  },
                  {
                    group: 'INSIGHTS',
                    roleRequired: 'Manager',
                    items: [
                      { id: 'dashboard', label: 'Analytics Dashboard', label_bn: 'ড্যাশবোর্ড', icon: 'barChart', shortcut: 'G D' },
                      { id: 'reports', label: 'Reports Directory', label_bn: 'রিপোর্ট সমূহ', icon: 'fileText', shortcut: 'G R' }
                    ]
                  },
                  {
                    group: 'ADMIN & SYSTEM',
                    roleRequired: 'Owner',
                    items: [
                      { id: 'users', label: 'Users & Roles', label_bn: 'ব্যবহারকারী ও পদবী', icon: 'shield', shortcut: '' },
                      { id: 'settings', label: 'Settings', label_bn: 'সেটিংস', icon: 'settings', shortcut: 'G ,' },
                      { id: 'backup', label: 'Backup & Restore', label_bn: 'ব্যাকআপ সংরক্ষণ', icon: 'database', shortcut: '' },
                      { id: 'audit_log', label: 'Audit Security Log', label_bn: 'নিরাপত্তা লগ', icon: 'lock', shortcut: '' },
                      { id: 'super_admin', label: 'SaaS Platform Admin', label_bn: 'সাস প্ল্যাটফর্ম অ্যাডমিন', icon: 'key', shortcut: '' },
                      { id: 'states_demo', label: 'States Demo', label_bn: 'স্টেট ডেমো', icon: 'palette', shortcut: '' }
                    ]
                  }
                ].map(section => {
                  const allowedItems = section.items.filter(item => {
                    if (item.id === 'super_admin') return viewAsRole === 'Super Admin' || viewAsRole === 'Owner';
                    return canAccess(item.id);
                  });
                  return { ...section, items: allowedItems };
                }).filter(section => section.items.length > 0).map((section, idx) => (
                  <div key={idx} className="px-2">
                    {!sidebarCollapsed && (
                      <div className="px-2.5 mb-1 text-[10px] font-bold text-[var(--muted-foreground)] tracking-wider uppercase">
                        {section.group}
                      </div>
                    )}
                    <div className="space-y-0.5">
                      {section.items.map(item => {
                        const isActive = currentScreen === item.id || 
                          (item.id === 'sales' && currentScreen === 'sale_detail') ||
                          (item.id === 'customers' && (currentScreen === 'customer_form' || currentScreen === 'customer_profile')) ||
                          (item.id === 'products' && (currentScreen === 'product_form' || currentScreen === 'product_detail'));
                        return (
                          <button
                            key={item.id}
                            onClick={() => {
                              if (item.id === 'pos') setPosMode('sale');
                              navigateToScreen(item.id);
                            }}
                            className={`w-full h-8 px-2.5 rounded-lg flex items-center justify-between text-xs transition-colors group ${
                              isActive 
                                ? 'bg-[var(--primary)] text-[var(--primary-foreground)] font-semibold shadow-xs' 
                                : 'text-[var(--foreground)] hover:bg-[var(--surface-sunken)]'
                            }`}
                            title={`${item.label} ${item.shortcut ? `(${item.shortcut})` : ''}`}
                          >
                            <div className="flex items-center gap-2.5 truncate">
                              <Icon name={item.icon} size={15} className={isActive ? 'text-inherit' : 'text-[var(--muted-foreground)] group-hover:text-[var(--foreground)]'} />
                              {!sidebarCollapsed && (
                                <span className="truncate">{lang === 'bn' ? item.label_bn : item.label}</span>
                              )}
                            </div>
                            {!sidebarCollapsed && item.shortcut && (
                              <kbd className={`text-[10px] font-mono px-1 py-0.2 rounded border ${
                                isActive ? 'bg-black/20 text-white border-transparent' : 'bg-[var(--surface)] text-[var(--muted-foreground)] border-[var(--border)]'
                              }`}>
                                {item.shortcut}
                              </kbd>
                            )}
                          </button>
                        );
                      })}
                    </div>
                  </div>
                ))}

              </div>

              {/* Sidebar Footer */}
              <div className="p-2 border-t border-[var(--border)]">
                <button
                  onClick={() => setShowShortcutsModal(true)}
                  className="w-full h-7 px-2 rounded-md hover:bg-[var(--surface-sunken)] text-xs text-[var(--muted-foreground)] flex items-center justify-between"
                  title="Keyboard Shortcuts (?)"
                >
                  <div className="flex items-center gap-2">
                    <Icon name="helpCircle" size={14} />
                    {!sidebarCollapsed && <span>Shortcuts Guide</span>}
                  </div>
                  {!sidebarCollapsed && <kbd className="text-[10px]">?</kbd>}
                </button>
              </div>
            </aside>

            {/* ================= MAIN CONTENT VIEWPORT ================= */}
            <main className="flex-1 flex flex-col overflow-hidden bg-[var(--background)]">
"""
