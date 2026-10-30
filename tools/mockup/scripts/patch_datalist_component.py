import re

with open('scripts/parts/icons_and_helpers.py', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Add icons to Icon component if missing
new_icons = """        download: <g><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></g>,
        columns: <g><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7m0-18H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h7m0-18v18" /></g>,
        arrowUpDown: <g><path d="m7 15 5 5 5-5"/><path d="m7 9 5-5 5 5"/></g>,
"""

if 'download:' not in content:
    content = content.replace('helpCircle: <g><circle cx="12" cy="12" r="10" /><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" /><line x1="12" y1="17" x2="12.01" y2="17" /></g>',
                              'helpCircle: <g><circle cx="12" cy="12" r="10" /><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" /><line x1="12" y1="17" x2="12.01" y2="17" /></g>,\n' + new_icons.strip())

# 2. Update Modal drawer styling
old_modal_return = """      return (
        <div
          className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4 animate-in fade-in duration-100"
          onClick={handleBackdrop}
          onKeyDown={handleKey}
          id={id ? `modal-${id}` : undefined}
        >
          <div
            ref={modalRef}
            role="dialog"
            aria-modal="true"
            className={`w-full ${sizeMap[size] || sizeMap.md} bg-[var(--surface-raised)] border border-[var(--border)] rounded-2xl shadow-2xl overflow-hidden flex flex-col text-xs animate-in zoom-in-95 duration-100 ${className} ${printable ? 'printable-area' : ''}`}
          >"""

new_modal_return = """      return (
        <div
          className={`fixed inset-0 bg-black/60 backdrop-blur-xs flex ${size === 'drawer' ? 'items-stretch justify-end p-0' : 'items-center justify-center p-4'} z-50 animate-in fade-in duration-100`}
          onClick={handleBackdrop}
          onKeyDown={handleKey}
          id={id ? `modal-${id}` : undefined}
        >
          <div
            ref={modalRef}
            role="dialog"
            aria-modal="true"
            className={`w-full ${sizeMap[size] || sizeMap.md} bg-[var(--surface-raised)] border border-[var(--border)] ${size === 'drawer' ? 'rounded-none shadow-2xl h-full' : 'rounded-2xl shadow-2xl'} overflow-hidden flex flex-col text-xs animate-in ${size === 'drawer' ? 'slide-in-from-right duration-200' : 'zoom-in-95 duration-100'} ${className} ${printable ? 'printable-area' : ''}`}
          >"""

if old_modal_return in content:
    content = content.replace(old_modal_return, new_modal_return)

# 3. Add DataList, formatSaleCustomer, matchesDatePreset, exportToCSV
helpers_and_datalist = '''
    // Helper to format customer representation in lists
    // Guarantees: Name + formatted no "1000 0249", NEVER "Customer #10000249"
    function formatSaleCustomer(sale) {
      if (!sale) return { name: 'Walk-in Customer', no: '' };
      if (sale.customer === 'Walk-in Customer' || sale.customer_no === 'Walk-in') {
        return { name: 'Walk-in Customer', no: '' };
      }
      
      let rawNo = (sale.customer_no || '').replace(/\\D/g, '');
      if (!rawNo && sale.customer && sale.customer.includes('#')) {
        rawNo = sale.customer.replace(/\\D/g, '');
      }

      if (rawNo.length === 8) {
        const formatted = `${rawNo.slice(0, 4)} ${rawNo.slice(4)}`;
        // Try to match with MOCK_CUSTOMERS if available
        if (typeof MOCK_CUSTOMERS !== 'undefined') {
          const cust = MOCK_CUSTOMERS.find(c => c.customer_no === formatted || c.customer_no.replace(/\\s+/g, '') === rawNo);
          if (cust) {
            return { name: cust.name_en, no: formatted };
          }
        }
        const cleanName = (sale.customer || '').replace(/^Customer\\s*#?\\d+/, '').trim();
        return { name: cleanName || `Customer ${formatted}`, no: formatted };
      }

      if (typeof MOCK_CUSTOMERS !== 'undefined') {
        const custByName = MOCK_CUSTOMERS.find(c => c.name_en === sale.customer);
        if (custByName) {
          return { name: custByName.name_en, no: custByName.customer_no };
        }
      }

      return { name: sale.customer || 'Customer', no: sale.customer_no || '' };
    }

    // Date Preset Filter Helper
    function matchesDatePreset(dateStr, preset, customStart, customEnd) {
      if (!preset || preset === 'All') return true;
      if (!dateStr) return true;

      const cleanStr = dateStr === 'Today' ? '29 Oct 2026' : (dateStr === 'Yesterday' ? '28 Oct 2026' : dateStr);
      const parsed = new Date(cleanStr);
      if (isNaN(parsed.getTime())) return true;

      // Mock dataset baseline anchor date: 29 Oct 2026
      const refDate = new Date('2026-10-29T23:59:59');

      if (preset === 'Today') {
        return cleanStr.includes('29 Oct 2026');
      }
      if (preset === 'Yesterday') {
        return cleanStr.includes('28 Oct 2026');
      }
      if (preset === 'This week') {
        const diffDays = (refDate.getTime() - parsed.getTime()) / (1000 * 60 * 60 * 24);
        return diffDays >= 0 && diffDays <= 7;
      }
      if (preset === 'This month') {
        return cleanStr.includes('Oct 2026');
      }
      if (preset === 'Custom range' && (customStart || customEnd)) {
        if (customStart && parsed < new Date(customStart)) return false;
        if (customEnd && parsed > new Date(customEnd + 'T23:59:59')) return false;
        return true;
      }
      return true;
    }

    // Standard CSV / Excel Exporter
    function exportToCSV(filename, columns, rows) {
      const visibleCols = columns.filter(c => c.id !== '_select' && c.id !== 'actions');
      const headers = visibleCols.map(c => `"${(c.header || c.label || c.id || '').replace(/"/g, '""')}"`).join(',');
      const lines = rows.map(row => {
        return visibleCols.map(c => {
          let val = c.exportValue ? c.exportValue(row) : (c.accessor ? c.accessor(row) : row[c.id]);
          if (typeof val === 'number') return val;
          return `"${(val !== undefined && val !== null ? String(val) : '').replace(/"/g, '""')}"`;
        }).join(',');
      });
      const csvContent = "\\uFEFF" + [headers, ...lines].join('\\r\\n');
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.setAttribute("href", url);
      link.setAttribute("download", filename);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    }

    // =========================================================================
    // UNIFIED REUSABLE <DataList> COMPONENT (PART 2 SPECIFICATION)
    // Applied to: Sales, Returns, Due Collection, Customers, Products, Stock,
    //             Purchases, Vendors, and Money Entries
    // =========================================================================
    function DataList({
      id = 'datalist',
      title,
      subtitle,
      data = [],
      keyField = 'id',
      columns = [],
      searchPlaceholder = 'Search records... (Press / to focus)',
      searchFields = [],
      dateField = 'date',
      datePresets = ['All', 'Today', 'Yesterday', 'This week', 'This month', 'Custom range'],
      initialDatePreset = 'All',
      filterChips = [],
      moreFilters = [],
      savedViews = [],
      bulkActions = [],
      headerActions = null,
      onRowClick = null,
      onRowContextMenu = null,
      initialSortField = '',
      initialSortDirection = 'none',
      pageSizeOptions = [25, 50, 100],
      initialPageSize = 25,
      emptyIllustration = 'search',
      emptyTitle = 'No records found',
      emptyMessage = 'Try adjusting your search query, date presets, or filters.',
      emptyCta = null,
      loading = false,
      customFilter = null,
      className = ''
    }) {
      const [searchQuery, setSearchQuery] = useState('');
      const [activeDatePreset, setActiveDatePreset] = useState(initialDatePreset);
      const [customStartDate, setCustomStartDate] = useState('');
      const [customEndDate, setCustomEndDate] = useState('');
      const [activeFilters, setActiveFilters] = useState({});
      const [activeSavedView, setActiveSavedView] = useState('default');
      const [sortField, setSortField] = useState(initialSortField);
      const [sortDirection, setSortDirection] = useState(initialSortDirection);
      const [pageSize, setPageSize] = useState(initialPageSize);
      const [currentPage, setCurrentPage] = useState(1);
      const [highlightedIndex, setHighlightedIndex] = useState(0);
      const [selectedIds, setSelectedIds] = useState([]);
      const [visibleColumns, setVisibleColumns] = useState(() =>
        columns.filter(c => c.defaultVisible !== false).map(c => c.id)
      );
      const [showColumnChooser, setShowColumnChooser] = useState(false);
      const [showMoreFiltersDrawer, setShowMoreFiltersDrawer] = useState(false);
      const [showExportMenu, setShowExportMenu] = useState(false);

      const searchInputRef = useRef(null);
      const tableContainerRef = useRef(null);
      const columnChooserRef = useRef(null);
      const exportMenuRef = useRef(null);

      // Keyboard Shortcut: '/' focuses search input globally for this table when visible
      useEffect(() => {
        const handleGlobalSlash = (e) => {
          const tag = document.activeElement?.tagName;
          if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)) return;
          if (e.key === '/') {
            e.preventDefault();
            if (searchInputRef.current) {
              searchInputRef.current.focus();
              searchInputRef.current.select();
            }
          }
        };
        window.addEventListener('keydown', handleGlobalSlash);
        return () => window.removeEventListener('keydown', handleGlobalSlash);
      }, []);

      // Close dropdowns on outside click
      useEffect(() => {
        const handleClickOutside = (e) => {
          if (columnChooserRef.current && !columnChooserRef.current.contains(e.target)) {
            setShowColumnChooser(false);
          }
          if (exportMenuRef.current && !exportMenuRef.current.contains(e.target)) {
            setShowExportMenu(false);
          }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
      }, []);

      // Filter Data
      const filteredData = useMemo(() => {
        return (data || []).filter(item => {
          // 1. Search Query
          if (searchQuery.trim()) {
            const q = searchQuery.toLowerCase().trim();
            let matches = false;
            if (searchFields.length > 0) {
              matches = searchFields.some(field => {
                const val = typeof field === 'function' ? field(item) : item[field];
                return val !== undefined && val !== null && String(val).toLowerCase().includes(q);
              });
            } else {
              matches = Object.values(item).some(val =>
                val !== undefined && val !== null && typeof val !== 'object' && String(val).toLowerCase().includes(q)
              );
            }
            if (!matches) return false;
          }

          // 2. Date Preset
          if (dateField && activeDatePreset !== 'All') {
            const rowDate = item[dateField];
            if (!matchesDatePreset(rowDate, activeDatePreset, customStartDate, customEndDate)) {
              return false;
            }
          }

          // 3. Active Filters (from chips & drawer)
          for (const [key, filterVal] of Object.entries(activeFilters)) {
            if (filterVal !== undefined && filterVal !== null && filterVal !== 'all' && filterVal !== '') {
              const itemVal = item[key];
              if (typeof filterVal === 'boolean') {
                if (Boolean(itemVal) !== filterVal) return false;
              } else if (typeof filterVal === 'string') {
                if (String(itemVal || '').toLowerCase() !== filterVal.toLowerCase()) {
                  // Fallback: check if includes
                  if (!String(itemVal || '').toLowerCase().includes(filterVal.toLowerCase())) {
                    return false;
                  }
                }
              }
            }
          }

          // 4. Custom filter predicate
          if (customFilter) {
            if (!customFilter(item, activeFilters, activeDatePreset, { customStartDate, customEndDate })) {
              return false;
            }
          }

          return true;
        });
      }, [data, searchQuery, searchFields, dateField, activeDatePreset, customStartDate, customEndDate, activeFilters, customFilter]);

      // Sort Data
      const filteredAndSortedData = useMemo(() => {
        if (!sortField || sortDirection === 'none') return filteredData;
        const col = columns.find(c => c.id === sortField);
        return [...filteredData].sort((a, b) => {
          let valA = col && col.accessor ? col.accessor(a) : a[sortField];
          let valB = col && col.accessor ? col.accessor(b) : b[sortField];

          if (valA === undefined || valA === null) valA = '';
          if (valB === undefined || valB === null) valB = '';

          if (typeof valA === 'number' && typeof valB === 'number') {
            return sortDirection === 'asc' ? valA - valB : valB - valA;
          }
          const strA = String(valA).toLowerCase();
          const strB = String(valB).toLowerCase();
          return sortDirection === 'asc' ? strA.localeCompare(strB) : strB.localeCompare(strA);
        });
      }, [filteredData, sortField, sortDirection, columns]);

      // Pagination Calculation
      const totalCount = filteredAndSortedData.length;
      const totalPages = Math.max(1, Math.ceil(totalCount / pageSize));
      const startIndex = (currentPage - 1) * pageSize;
      const paginatedData = useMemo(() => {
        return filteredAndSortedData.slice(startIndex, startIndex + pageSize);
      }, [filteredAndSortedData, startIndex, pageSize]);

      // Visible Column Definitions
      const visibleColDefs = useMemo(() => {
        return columns.filter(c => visibleColumns.includes(c.id));
      }, [columns, visibleColumns]);

      // Active filters count for drawer badge
      const activeFilterCount = useMemo(() => {
        let count = 0;
        Object.entries(activeFilters).forEach(([k, v]) => {
          if (v && v !== 'all' && v !== '') count++;
        });
        if (activeDatePreset !== 'All') count++;
        if (searchQuery.trim()) count++;
        return count;
      }, [activeFilters, activeDatePreset, searchQuery]);

      // Keyboard Row Navigation: Up/Down or J/K, Enter opens, Space/X selects, Shift+F10 context menu
      const handleTableKeyDown = (e) => {
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;

        if (e.key === 'ArrowDown' || e.key === 'j' || e.key === 'J') {
          e.preventDefault();
          setHighlightedIndex(prev => Math.min(paginatedData.length - 1, prev + 1));
          return;
        }
        if (e.key === 'ArrowUp' || e.key === 'k' || e.key === 'K') {
          e.preventDefault();
          setHighlightedIndex(prev => Math.max(0, prev - 1));
          return;
        }
        if (e.key === 'Enter') {
          const activeRow = paginatedData[highlightedIndex];
          if (activeRow && onRowClick) {
            e.preventDefault();
            onRowClick(activeRow);
          }
          return;
        }
        if (e.key === ' ' || e.key === 'x' || e.key === 'X') {
          e.preventDefault();
          const activeRow = paginatedData[highlightedIndex];
          if (activeRow) {
            const idVal = activeRow[keyField];
            setSelectedIds(prev =>
              prev.includes(idVal) ? prev.filter(x => x !== idVal) : [...prev, idVal]
            );
          }
          return;
        }
        if (e.shiftKey && e.key === 'F10') {
          e.preventDefault();
          const activeRow = paginatedData[highlightedIndex];
          if (activeRow && onRowContextMenu) {
            const rowEl = document.getElementById(`${id}-row-${highlightedIndex}`);
            const rect = rowEl?.getBoundingClientRect();
            const simulatedEvent = {
              preventDefault: () => {},
              stopPropagation: () => {},
              clientX: rect ? Math.round(rect.left + 100) : 250,
              clientY: rect ? Math.round(rect.top + 20) : 250
            };
            onRowContextMenu(activeRow, simulatedEvent);
          }
          return;
        }
      };

      const handleHeaderSort = (col) => {
        if (!col.sortable) return;
        if (sortField !== col.id) {
          setSortField(col.id);
          setSortDirection('asc');
        } else if (sortDirection === 'asc') {
          setSortDirection('desc');
        } else if (sortDirection === 'desc') {
          setSortField('');
          setSortDirection('none');
        } else {
          setSortDirection('asc');
        }
      };

      const clearAllFilters = () => {
        setSearchQuery('');
        setActiveFilters({});
        setActiveDatePreset('All');
        setCustomStartDate('');
        setCustomEndDate('');
        setActiveSavedView('default');
        setCurrentPage(1);
        setHighlightedIndex(0);
      };

      return (
        <div className={`flex-1 flex flex-col overflow-hidden p-4 space-y-3 ${className}`}>
          {/* Header Bar */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 shrink-0">
            <div>
              <div className="flex items-center gap-2">
                <h1 className="text-lg font-bold text-[var(--foreground)]">{title}</h1>
                <span className="px-2 py-0.5 rounded-full text-[11px] font-mono font-bold bg-[var(--surface-sunken)] border border-[var(--border)] text-[var(--muted-foreground)]">
                  {totalCount}
                </span>
              </div>
              {subtitle && <div className="text-xs text-[var(--muted-foreground)] mt-0.5">{subtitle}</div>}
            </div>

            <div className="flex items-center gap-2">
              {headerActions}

              {/* Column Chooser */}
              <div className="relative" ref={columnChooserRef}>
                <button
                  type="button"
                  onClick={() => setShowColumnChooser(prev => !prev)}
                  className="h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] text-xs font-medium flex items-center gap-1.5 text-[var(--muted-foreground)] hover:text-[var(--foreground)] transition-colors cursor-pointer"
                  title="Choose Columns"
                >
                  <Icon name="columns" size={13} />
                  <span className="hidden sm:inline">Columns</span>
                </button>
                {showColumnChooser && (
                  <div className="absolute right-0 top-9 w-52 bg-[var(--surface-raised)] border border-[var(--border)] rounded-xl shadow-2xl z-40 p-2 text-xs space-y-1 animate-in fade-in zoom-in-95 duration-100">
                    <div className="font-semibold text-[11px] text-[var(--muted-foreground)] px-1.5 py-0.5 border-b border-[var(--border)] mb-1 flex items-center justify-between">
                      <span>Visible Columns</span>
                      <button
                        type="button"
                        onClick={() => setVisibleColumns(columns.map(c => c.id))}
                        className="text-[10px] text-[var(--primary)] hover:underline cursor-pointer"
                      >
                        Reset All
                      </button>
                    </div>
                    <div className="max-h-60 overflow-y-auto space-y-0.5">
                      {columns.filter(c => c.id !== '_select' && c.hideable !== false).map(c => {
                        const isChecked = visibleColumns.includes(c.id);
                        return (
                          <label key={c.id} className="flex items-center gap-2 px-1.5 py-1 rounded hover:bg-[var(--surface-sunken)] cursor-pointer select-none">
                            <input
                              type="checkbox"
                              checked={isChecked}
                              onChange={() => {
                                setVisibleColumns(prev =>
                                  isChecked ? prev.filter(colId => colId !== c.id) : [...prev, c.id]
                                );
                              }}
                              className="rounded border-[var(--border)] text-[var(--primary)] focus:ring-[var(--primary)]"
                            />
                            <span className="text-[11px] text-[var(--foreground)] truncate">{c.header || c.label || c.id}</span>
                          </label>
                        );
                      })}
                    </div>
                  </div>
                )}
              </div>

              {/* Export Menu */}
              <div className="relative" ref={exportMenuRef}>
                <button
                  type="button"
                  onClick={() => setShowExportMenu(prev => !prev)}
                  className="h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] text-xs font-medium flex items-center gap-1.5 text-[var(--muted-foreground)] hover:text-[var(--foreground)] transition-colors cursor-pointer"
                  title="Export Options"
                >
                  <Icon name="download" size={13} />
                  <span className="hidden sm:inline">Export</span>
                  <Icon name="chevronDown" size={11} />
                </button>
                {showExportMenu && (
                  <div className="absolute right-0 top-9 w-44 bg-[var(--surface-raised)] border border-[var(--border)] rounded-xl shadow-2xl z-40 p-1 text-xs animate-in fade-in zoom-in-95 duration-100">
                    <button
                      type="button"
                      onClick={() => {
                        exportToCSV(`${id}_export_${Date.now()}.csv`, visibleColDefs, filteredAndSortedData);
                        setShowExportMenu(false);
                      }}
                      className="w-full text-left px-2.5 py-1.5 rounded-lg hover:bg-[var(--surface-sunken)] flex items-center gap-2 text-[var(--foreground)] cursor-pointer"
                    >
                      <Icon name="fileText" size={13} className="text-[var(--success)]" />
                      <span>Export Excel (.csv)</span>
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        exportToCSV(`${id}_data_${Date.now()}.csv`, visibleColDefs, filteredAndSortedData);
                        setShowExportMenu(false);
                      }}
                      className="w-full text-left px-2.5 py-1.5 rounded-lg hover:bg-[var(--surface-sunken)] flex items-center gap-2 text-[var(--foreground)] cursor-pointer"
                    >
                      <Icon name="fileText" size={13} className="text-[var(--info)]" />
                      <span>Export to CSV</span>
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        setShowExportMenu(false);
                        window.print();
                      }}
                      className="w-full text-left px-2.5 py-1.5 rounded-lg hover:bg-[var(--surface-sunken)] flex items-center gap-2 text-[var(--foreground)] cursor-pointer border-t border-[var(--border)] mt-0.5"
                    >
                      <Icon name="printer" size={13} className="text-[var(--primary)]" />
                      <span>Export to PDF / Print</span>
                    </button>
                  </div>
                )}
              </div>

              {/* Print Action */}
              <button
                type="button"
                onClick={() => window.print()}
                className="h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] text-xs font-medium flex items-center gap-1.5 text-[var(--muted-foreground)] hover:text-[var(--foreground)] transition-colors cursor-pointer"
                title="Print List"
              >
                <Icon name="printer" size={13} />
                <span className="hidden sm:inline">Print</span>
              </button>
            </div>
          </div>

          {/* Controls Bar: Search + Filter Chips + Saved Views + More Filters */}
          <div className="p-2.5 rounded-xl bg-[var(--surface)] border border-[var(--border)] flex flex-wrap items-center justify-between gap-2 shrink-0">
            <div className="flex flex-wrap items-center gap-2 flex-1 min-w-[260px]">
              {/* Search Box */}
              <div className="relative flex-1 min-w-[200px] max-w-sm">
                <input
                  ref={searchInputRef}
                  type="text"
                  value={searchQuery}
                  onChange={(e) => {
                    setSearchQuery(e.target.value);
                    setCurrentPage(1);
                    setHighlightedIndex(0);
                  }}
                  onKeyDown={(e) => {
                    if (e.key === 'Escape') searchInputRef.current?.blur();
                  }}
                  placeholder={searchPlaceholder}
                  className="w-full h-8 pl-8 pr-7 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] focus:border-[var(--primary)] focus:outline-none"
                />
                <Icon name="search" size={13} className="absolute left-2.5 top-2.5 text-[var(--muted-foreground)] pointer-events-none" />
                {searchQuery ? (
                  <button
                    type="button"
                    onClick={() => {
                      setSearchQuery('');
                      searchInputRef.current?.focus();
                    }}
                    className="absolute right-2 top-2 text-[var(--muted-foreground)] hover:text-[var(--foreground)] cursor-pointer"
                    title="Clear search"
                  >
                    <Icon name="x" size={14} />
                  </button>
                ) : (
                  <kbd className="absolute right-2 top-2 text-[9px] text-[var(--muted-foreground)] font-mono pointer-events-none px-1 rounded bg-[var(--surface)] border border-[var(--border)]">/</kbd>
                )}
              </div>

              {/* Saved Views Dropdown */}
              {savedViews.length > 0 && (
                <div className="flex items-center gap-1">
                  <span className="text-[11px] text-[var(--muted-foreground)] font-medium">View:</span>
                  <select
                    value={activeSavedView}
                    onChange={(e) => {
                      const viewId = e.target.value;
                      setActiveSavedView(viewId);
                      const view = savedViews.find(v => v.id === viewId);
                      if (view) {
                        if (view.filters) setActiveFilters(view.filters);
                        else setActiveFilters({});
                        if (view.datePreset) setActiveDatePreset(view.datePreset);
                        if (view.sortField) {
                          setSortField(view.sortField);
                          setSortDirection(view.sortDirection || 'desc');
                        }
                        if (view.search !== undefined) setSearchQuery(view.search);
                      } else {
                        setActiveFilters({});
                        setActiveDatePreset(initialDatePreset);
                        setSearchQuery('');
                      }
                      setCurrentPage(1);
                      setHighlightedIndex(0);
                    }}
                    className="h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-medium focus:border-[var(--primary)] cursor-pointer"
                  >
                    {savedViews.map(v => (
                      <option key={v.id} value={v.id}>{v.label}</option>
                    ))}
                  </select>
                </div>
              )}

              {/* Date Presets Selector */}
              {datePresets.length > 0 && (
                <div className="flex items-center gap-1">
                  <span className="text-[11px] text-[var(--muted-foreground)] font-medium">Date:</span>
                  <select
                    value={activeDatePreset}
                    onChange={(e) => {
                      setActiveDatePreset(e.target.value);
                      setCurrentPage(1);
                      setHighlightedIndex(0);
                    }}
                    className="h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-medium focus:border-[var(--primary)] cursor-pointer"
                  >
                    {datePresets.map(preset => (
                      <option key={preset} value={preset}>{preset}</option>
                    ))}
                  </select>
                  {activeDatePreset === 'Custom range' && (
                    <div className="flex items-center gap-1 ml-1">
                      <input
                        type="date"
                        value={customStartDate}
                        onChange={(e) => { setCustomStartDate(e.target.value); setCurrentPage(1); }}
                        className="h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                      />
                      <span className="text-[10px] text-[var(--muted-foreground)]">to</span>
                      <input
                        type="date"
                        value={customEndDate}
                        onChange={(e) => { setCustomEndDate(e.target.value); setCurrentPage(1); }}
                        className="h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                      />
                    </div>
                  )}
                </div>
              )}

              {/* Quick Filter Chips */}
              {filterChips.map(chipGroup => (
                <div key={chipGroup.id} className="flex items-center gap-1">
                  {chipGroup.options.map(opt => {
                    const isSelected = (activeFilters[chipGroup.id] || 'all') === opt.value;
                    return (
                      <button
                        key={opt.value}
                        type="button"
                        onClick={() => {
                          setActiveFilters(prev => ({ ...prev, [chipGroup.id]: opt.value }));
                          setCurrentPage(1);
                          setHighlightedIndex(0);
                        }}
                        className={`h-7 px-2.5 rounded-lg text-xs font-medium transition-colors cursor-pointer ${
                          isSelected
                            ? 'bg-[var(--primary)] text-[var(--primary-foreground)] font-bold shadow-xs'
                            : 'bg-[var(--surface-sunken)] text-[var(--muted-foreground)] hover:text-[var(--foreground)]'
                        }`}
                      >
                        {opt.label}
                      </button>
                    );
                  })}
                </div>
              ))}
            </div>

            {/* "More Filters" Button */}
            {moreFilters.length > 0 && (
              <button
                type="button"
                onClick={() => setShowMoreFiltersDrawer(true)}
                className={`h-8 px-3 rounded-lg border text-xs font-medium flex items-center gap-1.5 transition-colors cursor-pointer ${
                  activeFilterCount > 0
                    ? 'bg-[var(--primary-soft)] border-[var(--primary)] text-[var(--primary)] font-bold'
                    : 'bg-[var(--surface-sunken)] border-[var(--border)] text-[var(--muted-foreground)] hover:text-[var(--foreground)]'
                }`}
                title="Open More Filters Drawer"
              >
                <Icon name="filter" size={13} />
                <span>More Filters</span>
                {activeFilterCount > 0 && (
                  <span className="w-4 h-4 rounded-full bg-[var(--primary)] text-white text-[10px] font-bold flex items-center justify-center font-mono">
                    {activeFilterCount}
                  </span>
                )}
              </button>
            )}
          </div>

          {/* Table Container with Keyboard Navigation */}
          <div
            ref={tableContainerRef}
            tabIndex={0}
            onKeyDown={handleTableKeyDown}
            className="flex-1 rounded-xl bg-[var(--surface)] border border-[var(--border)] overflow-hidden flex flex-col shadow-xs focus:outline-none focus:ring-1 focus:ring-[var(--primary)]"
          >
            <div className="flex-1 overflow-y-auto overflow-x-auto">
              <table className="w-full text-left border-collapse text-xs">
                {/* Header */}
                <thead className="bg-[var(--surface-sunken)] sticky top-0 border-b border-[var(--border)] select-none text-[var(--muted-foreground)] font-semibold z-10">
                  <tr>
                    {/* Row Select Checkbox Column */}
                    <th className="py-2.5 px-3 w-8">
                      <input
                        type="checkbox"
                        checked={paginatedData.length > 0 && paginatedData.every(r => selectedIds.includes(r[keyField]))}
                        onChange={() => {
                          const allSelected = paginatedData.every(r => selectedIds.includes(r[keyField]));
                          if (allSelected) {
                            const pageIds = paginatedData.map(r => r[keyField]);
                            setSelectedIds(prev => prev.filter(id => !pageIds.includes(id)));
                          } else {
                            const newIds = paginatedData.map(r => r[keyField]);
                            setSelectedIds(prev => Array.from(new Set([...prev, ...newIds])));
                          }
                        }}
                        className="rounded border-[var(--border)] text-[var(--primary)] focus:ring-[var(--primary)] cursor-pointer"
                        title="Select All on this page"
                      />
                    </th>

                    {visibleColDefs.map(col => (
                      <th
                        key={col.id}
                        onClick={() => handleHeaderSort(col)}
                        className={`py-2.5 px-3 text-[11px] font-semibold text-[var(--muted-foreground)] select-none ${
                          col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'
                        } ${col.sortable ? 'cursor-pointer hover:text-[var(--foreground)] hover:bg-[var(--surface)]' : ''}`}
                      >
                        <div className={`flex items-center gap-1 ${
                          col.align === 'right' ? 'justify-end' : col.align === 'center' ? 'justify-center' : 'justify-start'
                        }`}>
                          <span>{col.header || col.label}</span>
                          {col.sortable && (
                            <span className="text-[10px]">
                              {sortField === col.id && sortDirection === 'asc' ? '▲' :
                               sortField === col.id && sortDirection === 'desc' ? '▼' :
                               <span className="text-[var(--muted-foreground)]/40 font-mono">↕</span>}
                            </span>
                          )}
                        </div>
                      </th>
                    ))}
                  </tr>
                </thead>

                {/* Body: Skeleton Loading, Empty State, or Real Rows */}
                {loading ? (
                  <tbody className="divide-y divide-[var(--border)]">
                    {[1, 2, 3, 4, 5].map(i => (
                      <tr key={i} className="animate-pulse">
                        <td className="py-3 px-3"><div className="w-4 h-4 bg-[var(--border)] rounded"></div></td>
                        {visibleColDefs.map(col => (
                          <td key={col.id} className="py-3 px-3">
                            <div className="h-4 bg-[var(--border)] rounded w-3/4"></div>
                          </td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                ) : paginatedData.length === 0 ? (
                  <tbody>
                    <tr>
                      <td colSpan={visibleColDefs.length + 1} className="py-12 text-center">
                        <div className="flex flex-col items-center justify-center max-w-sm mx-auto space-y-2">
                          <div className="w-12 h-12 rounded-full bg-[var(--surface-sunken)] text-[var(--muted-foreground)] flex items-center justify-center mb-1">
                            <Icon name={emptyIllustration || "search"} size={24} />
                          </div>
                          <div className="font-bold text-sm text-[var(--foreground)]">{emptyTitle}</div>
                          <div className="text-xs text-[var(--muted-foreground)]">{emptyMessage}</div>
                          <button
                            type="button"
                            onClick={() => {
                              if (emptyCta?.action) emptyCta.action();
                              else clearAllFilters();
                            }}
                            className="mt-2 h-7 px-3 rounded-lg bg-[var(--primary-soft)] text-[var(--primary)] hover:bg-[var(--primary)] hover:text-white text-xs font-semibold transition-colors cursor-pointer"
                          >
                            {emptyCta?.label || 'Clear all filters'}
                          </button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                ) : (
                  <tbody className="divide-y divide-[var(--border)]">
                    {paginatedData.map((row, index) => {
                      const isHighlighted = highlightedIndex === index;
                      const isSelected = selectedIds.includes(row[keyField]);
                      return (
                        <tr
                          id={`${id}-row-${index}`}
                          key={row[keyField] || index}
                          onClick={() => {
                            setHighlightedIndex(index);
                            if (onRowClick) onRowClick(row);
                          }}
                          onContextMenu={(e) => {
                            setHighlightedIndex(index);
                            if (onRowContextMenu) onRowContextMenu(row, e);
                          }}
                          className={`transition-colors cursor-pointer select-none group ${
                            isSelected
                              ? 'bg-[var(--primary-soft)]/25'
                              : isHighlighted
                                ? 'bg-[var(--surface-sunken)] ring-1 ring-inset ring-[var(--primary)]'
                                : 'hover:bg-[var(--surface-sunken)]'
                          }`}
                        >
                          {/* Row Checkbox */}
                          <td className="py-2.5 px-3 w-8" onClick={(e) => e.stopPropagation()}>
                            <input
                              type="checkbox"
                              checked={isSelected}
                              onChange={() => {
                                const idVal = row[keyField];
                                setSelectedIds(prev =>
                                  prev.includes(idVal) ? prev.filter(x => x !== idVal) : [...prev, idVal]
                                );
                              }}
                              className="rounded border-[var(--border)] text-[var(--primary)] focus:ring-[var(--primary)] cursor-pointer"
                            />
                          </td>

                          {visibleColDefs.map(col => {
                            const val = col.accessor ? col.accessor(row) : row[col.id];
                            return (
                              <td
                                key={col.id}
                                className={`py-2.5 px-3 ${col.className || ''} ${
                                  col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'
                                }`}
                              >
                                {col.cell ? (
                                  col.cell(row)
                                ) : col.type === 'money' ? (
                                  <span className="font-sans font-bold tabular-nums num text-[var(--foreground)]">
                                    {formatMoney(val)}
                                  </span>
                                ) : col.type === 'qty' || col.type === 'number' ? (
                                  <span className="font-mono font-medium num text-[var(--foreground)]">
                                    {val !== undefined && val !== null ? val : '—'}
                                  </span>
                                ) : (
                                  val !== undefined && val !== null ? String(val) : '—'
                                )}
                              </td>
                            );
                          })}
                        </tr>
                      );
                    })}
                  </tbody>
                )}

                {/* TOTALS FOOTER ROW: Mandatory for every money & qty column */}
                <tfoot className="bg-[var(--surface-sunken)] sticky bottom-0 border-t-2 border-[var(--border)] font-bold text-xs select-none z-10">
                  <tr>
                    <td className="py-2.5 px-3"></td>
                    {visibleColDefs.map((col, idx) => {
                      if (col.type === 'money') {
                        const sum = filteredAndSortedData.reduce((acc, row) => {
                          const val = col.accessor ? col.accessor(row) : row[col.id];
                          return acc + (Number(val) || 0);
                        }, 0);
                        return (
                          <td key={col.id} className="py-2.5 px-3 text-right font-sans font-bold tabular-nums num text-[var(--foreground)]">
                            {formatMoney(sum)}
                          </td>
                        );
                      }
                      if (col.type === 'qty' || col.type === 'number') {
                        const sum = filteredAndSortedData.reduce((acc, row) => {
                          const val = col.accessor ? col.accessor(row) : row[col.id];
                          return acc + (Number(val) || 0);
                        }, 0);
                        return (
                          <td key={col.id} className={`py-2.5 px-3 font-mono font-bold num text-[var(--foreground)] ${
                            col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'
                          }`}>
                            {sum.toLocaleString()}
                          </td>
                        );
                      }
                      if (idx === 0) {
                        return (
                          <td key={col.id} className="py-2.5 px-3 text-[var(--foreground)] font-semibold">
                            Total ({filteredAndSortedData.length})
                          </td>
                        );
                      }
                      return <td key={col.id} className="py-2.5 px-3"></td>;
                    })}
                  </tr>
                </tfoot>
              </table>
            </div>

            {/* Pagination Controls */}
            <div className="p-2.5 px-3 border-t border-[var(--border)] bg-[var(--surface)] flex flex-wrap items-center justify-between gap-2 shrink-0 select-none text-xs">
              <div className="flex items-center gap-2">
                <span className="text-[11px] text-[var(--muted-foreground)]">Rows per page:</span>
                <select
                  value={pageSize}
                  onChange={(e) => {
                    setPageSize(Number(e.target.value));
                    setCurrentPage(1);
                    setHighlightedIndex(0);
                  }}
                  className="h-7 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-medium text-[var(--foreground)] cursor-pointer"
                >
                  {pageSizeOptions.map(size => (
                    <option key={size} value={size}>{size}</option>
                  ))}
                </select>
              </div>

              <div className="flex items-center gap-3">
                <span className="text-[11px] text-[var(--muted-foreground)] font-mono">
                  {filteredAndSortedData.length === 0 ? '0 of 0' : `${startIndex + 1}–${Math.min(startIndex + pageSize, filteredAndSortedData.length)} of ${filteredAndSortedData.length}`}
                </span>
                <div className="flex items-center gap-1">
                  <button
                    type="button"
                    onClick={() => {
                      setCurrentPage(prev => Math.max(1, prev - 1));
                      setHighlightedIndex(0);
                    }}
                    disabled={currentPage <= 1}
                    className="h-7 w-7 rounded-lg border border-[var(--border)] bg-[var(--surface-sunken)] hover:bg-[var(--surface)] disabled:opacity-30 disabled:pointer-events-none flex items-center justify-center text-[var(--foreground)] cursor-pointer transition-colors"
                    title="Previous Page"
                  >
                    ‹
                  </button>
                  <span className="text-[11px] font-semibold text-[var(--foreground)] px-1 font-mono">
                    {currentPage} / {totalPages}
                  </span>
                  <button
                    type="button"
                    onClick={() => {
                      setCurrentPage(prev => Math.min(totalPages, prev + 1));
                      setHighlightedIndex(0);
                    }}
                    disabled={currentPage >= totalPages}
                    className="h-7 w-7 rounded-lg border border-[var(--border)] bg-[var(--surface-sunken)] hover:bg-[var(--surface)] disabled:opacity-30 disabled:pointer-events-none flex items-center justify-center text-[var(--foreground)] cursor-pointer transition-colors"
                    title="Next Page"
                  >
                    ›
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* Bulk Actions Floating Bar */}
          {selectedIds.length > 0 && (
            <div className="p-2.5 px-4 rounded-xl bg-[var(--surface-raised)] border border-[var(--primary)] shadow-2xl flex items-center justify-between gap-3 animate-in fade-in slide-in-from-bottom-2 duration-150 shrink-0">
              <div className="flex items-center gap-3">
                <span className="w-6 h-6 rounded-full bg-[var(--primary)] text-white text-[11px] font-bold flex items-center justify-center font-mono">
                  {selectedIds.length}
                </span>
                <span className="text-xs font-semibold text-[var(--foreground)]">
                  {selectedIds.length} item{selectedIds.length > 1 ? 's' : ''} selected
                </span>
                <button
                  type="button"
                  onClick={() => setSelectedIds([])}
                  className="text-[11px] text-[var(--muted-foreground)] hover:text-[var(--danger)] hover:underline ml-1 cursor-pointer"
                >
                  Clear selection
                </button>
              </div>
              <div className="flex items-center gap-2">
                {bulkActions.map((action, i) => (
                  <button
                    key={i}
                    type="button"
                    onClick={() => {
                      const selectedRows = filteredAndSortedData.filter(r => selectedIds.includes(r[keyField]));
                      action.action(selectedRows);
                    }}
                    className="h-7 px-3 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] hover:bg-[var(--primary-hover)] text-xs font-semibold flex items-center gap-1.5 transition-colors cursor-pointer shadow-xs"
                  >
                    {action.icon && <Icon name={action.icon} size={13} />}
                    <span>{action.label}</span>
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* More Filters Drawer */}
          <Modal
            isOpen={showMoreFiltersDrawer}
            onClose={() => setShowMoreFiltersDrawer(false)}
            title={`More Filters · ${title || 'Records'}`}
            subtitle="Filter by specific attributes, categories, branches, and flags"
            icon="filter"
            size="drawer"
            footer={
              <div className="flex items-center justify-between w-full">
                <button
                  type="button"
                  onClick={() => {
                    setActiveFilters({});
                    setActiveDatePreset('All');
                    setCustomStartDate('');
                    setCustomEndDate('');
                    setCurrentPage(1);
                  }}
                  className="px-3 py-1.5 rounded-lg border border-[var(--border)] text-xs text-[var(--muted-foreground)] hover:text-[var(--danger)] hover:border-[var(--danger)] transition-colors cursor-pointer"
                >
                  Clear All Filters
                </button>
                <button
                  type="button"
                  onClick={() => setShowMoreFiltersDrawer(false)}
                  className="px-4 py-1.5 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold hover:bg-[var(--primary-hover)] transition-colors cursor-pointer"
                >
                  Apply Filters
                </button>
              </div>
            }
          >
            <div className="space-y-4">
              {moreFilters.map((filter) => (
                <div key={filter.id} className="space-y-1.5">
                  <label className="font-semibold text-xs text-[var(--foreground)] block">
                    {filter.label}
                  </label>
                  {filter.type === 'select' || !filter.type ? (
                    <select
                      value={activeFilters[filter.id] || 'all'}
                      onChange={(e) => {
                        setActiveFilters(prev => ({ ...prev, [filter.id]: e.target.value }));
                        setCurrentPage(1);
                      }}
                      className="w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-medium focus:border-[var(--primary)]"
                    >
                      {(filter.options || []).map((opt) => (
                        <option key={opt.value} value={opt.value}>
                          {opt.label}
                        </option>
                      ))}
                    </select>
                  ) : filter.type === 'radio' ? (
                    <div className="space-y-1">
                      {(filter.options || []).map((opt) => (
                        <label key={opt.value} className="flex items-center gap-2 text-xs text-[var(--foreground)] cursor-pointer">
                          <input
                            type="radio"
                            name={filter.id}
                            value={opt.value}
                            checked={(activeFilters[filter.id] || 'all') === opt.value}
                            onChange={(e) => {
                              setActiveFilters(prev => ({ ...prev, [filter.id]: e.target.value }));
                              setCurrentPage(1);
                            }}
                            className="text-[var(--primary)] focus:ring-[var(--primary)]"
                          />
                          <span>{opt.label}</span>
                        </label>
                      ))}
                    </div>
                  ) : null}
                </div>
              ))}
            </div>
          </Modal>
        </div>
      );
    }
'''

# Append helpers and DataList to icons_and_helpers.py
if 'function DataList(' not in content:
    # Insert before the closing quote
    content = content.replace('"""\n\n# scripts/parts/render_shell.py', helpers_and_datalist + '\n"""\n\n# scripts/parts/render_shell.py')
    if 'function DataList(' not in content:
        # If the above replacement didn't match, replace the last """
        last_quote = content.rfind('"""')
        content = content[:last_quote] + helpers_and_datalist + '\n' + content[last_quote:]

with open('scripts/parts/icons_and_helpers.py', 'w', encoding='utf-8') as f:
    f.write(content)

print("icons_and_helpers.py patched successfully!")
