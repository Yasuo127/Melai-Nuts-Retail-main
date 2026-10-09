/// Server-computed sales figures for the owner screens
/// (`owner_sales_summary`). Nothing here is calculated on the device.
library;

double _d(dynamic v) => (v as num?)?.toDouble() ?? 0;
int _i(dynamic v) => (v as num?)?.toInt() ?? 0;

/// One branch's sales: today, the last 7 days, and the requested window.
class OwnerBranchSales {
  final String branchId;
  final String name;
  final double todayRevenue;
  final double weekRevenue;
  final double windowRevenue;
  final int ordersToday;
  final int ordersWeek;
  final int ordersWindow;

  const OwnerBranchSales({
    required this.branchId,
    required this.name,
    required this.todayRevenue,
    required this.weekRevenue,
    required this.windowRevenue,
    required this.ordersToday,
    required this.ordersWeek,
    required this.ordersWindow,
  });

  factory OwnerBranchSales.fromJson(Map<String, dynamic> j) => OwnerBranchSales(
    branchId: (j['branch_id'] as String?) ?? '',
    name: (j['name'] as String?) ?? '',
    todayRevenue: _d(j['today_revenue']),
    weekRevenue: _d(j['week_revenue']),
    windowRevenue: _d(j['window_revenue']),
    ordersToday: _i(j['orders_today']),
    ordersWeek: _i(j['orders_week']),
    ordersWindow: _i(j['orders_window']),
  );
}

/// Item sales for one product over the window (quantity x unit price, before
/// vouchers and delivery fees). Grouped by the product name on the order.
class OwnerProductSales {
  final String productName;
  final int units;
  final double revenue;

  const OwnerProductSales({
    required this.productName,
    required this.units,
    required this.revenue,
  });

  factory OwnerProductSales.fromJson(Map<String, dynamic> j) => OwnerProductSales(
    productName: (j['product_name'] as String?) ?? '',
    units: _i(j['units']),
    revenue: _d(j['revenue']),
  );
}

/// Order revenue for one calendar day (Asia/Manila).
class OwnerDailySales {
  final DateTime date;
  final double revenue;

  const OwnerDailySales({required this.date, required this.revenue});

  factory OwnerDailySales.fromJson(Map<String, dynamic> j) => OwnerDailySales(
    date: DateTime.parse(j['date'] as String),
    revenue: _d(j['revenue']),
  );
}

class OwnerSalesSummary {
  final int days;
  final List<OwnerBranchSales> branches;
  final List<OwnerProductSales> products;
  final List<OwnerDailySales> daily;

  const OwnerSalesSummary({
    required this.days,
    required this.branches,
    required this.products,
    required this.daily,
  });

  factory OwnerSalesSummary.fromJson(Map<String, dynamic> j) {
    List<Map<String, dynamic>> rows(dynamic v) => [
      for (final e in (v as List? ?? const [])) Map<String, dynamic>.from(e as Map),
    ];
    return OwnerSalesSummary(
      days: _i(j['days']),
      branches: rows(j['branches']).map(OwnerBranchSales.fromJson).toList(),
      products: rows(j['products']).map(OwnerProductSales.fromJson).toList(),
      daily: rows(j['daily']).map(OwnerDailySales.fromJson).toList(),
    );
  }

  /// Item sales across the listed (top 50) products.
  double get productRevenue => products.fold<double>(0, (sum, p) => sum + p.revenue);
  int get productUnits => products.fold<int>(0, (sum, p) => sum + p.units);
}