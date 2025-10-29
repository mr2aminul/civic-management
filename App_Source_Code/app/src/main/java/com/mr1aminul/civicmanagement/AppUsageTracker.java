package com.mr1aminul.civicmanagement;

import android.app.AppOpsManager;
import android.app.usage.UsageStats;
import android.app.usage.UsageStatsManager;
import android.content.Context;
import android.content.pm.ApplicationInfo;
import android.content.pm.PackageInfo;
import android.content.pm.PackageManager;
import android.os.Build;
import android.util.Log;

import com.mr1aminul.civicmanagement.database.DatabaseManager;

import java.util.ArrayList;
import java.util.Calendar;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

/**
 * Tracks app usage and installed applications
 * Requires PACKAGE_USAGE_STATS and QUERY_ALL_PACKAGES permissions
 */
public class AppUsageTracker {
    private static final String TAG = "AppUsageTracker";
    private Context context;
    private DatabaseManager dbManager;
    private UsageStatsManager usageStatsManager;
    private PackageManager packageManager;
    
    public AppUsageTracker(Context context) {
        this.context = context.getApplicationContext();
        this.dbManager = DatabaseManager.getInstance(context);
        this.packageManager = context.getPackageManager();
        
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            this.usageStatsManager = (UsageStatsManager) context.getSystemService(Context.USAGE_STATS_SERVICE);
        }
    }
    
    /**
     * Get app usage statistics for the last N days
     */
    public List<Map<String, Object>> getAppUsageStats(int days) {
        List<Map<String, Object>> appStats = new ArrayList<>();
        
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.LOLLIPOP) {
            Log.w(TAG, "App usage stats not available on this Android version");
            return appStats;
        }
        
        try {
            if (!hasUsageStatsPermission()) {
                Log.w(TAG, "PACKAGE_USAGE_STATS permission not granted");
                return appStats;
            }
            
            Calendar calendar = Calendar.getInstance();
            long endTime = calendar.getTimeInMillis();
            calendar.add(Calendar.DAY_OF_YEAR, -days);
            long startTime = calendar.getTimeInMillis();
            
            List<UsageStats> stats = usageStatsManager.queryUsageStats(
                    UsageStatsManager.INTERVAL_DAILY, startTime, endTime);
            
            if (stats == null) {
                Log.w(TAG, "No usage stats available");
                return appStats;
            }
            
            for (UsageStats usageStats : stats) {
                String packageName = usageStats.getPackageName();
                long totalTimeInForeground = usageStats.getTotalTimeInForeground();
                
                if (totalTimeInForeground > 0) {
                    String appName = getAppName(packageName);
                    
                    Map<String, Object> stat = new HashMap<>();
                    stat.put("package_name", packageName);
                    stat.put("app_name", appName);
                    stat.put("usage_time", totalTimeInForeground);
                    stat.put("last_used", usageStats.getLastTimeUsed());
                    
                    appStats.add(stat);
                }
            }
            
            Log.d(TAG, "Retrieved " + appStats.size() + " app usage stats");
            
        } catch (Exception e) {
            Log.e(TAG, "Error getting app usage stats", e);
        }
        
        return appStats;
    }
    
    /**
     * Get list of installed applications
     */
    public List<Map<String, Object>> getInstalledApps() {
        List<Map<String, Object>> installedApps = new ArrayList<>();
        
        try {
            List<ApplicationInfo> packages = packageManager.getInstalledApplications(
                    PackageManager.GET_META_DATA);
            
            for (ApplicationInfo appInfo : packages) {
                // Skip system apps if needed
                boolean isSystemApp = (appInfo.flags & ApplicationInfo.FLAG_SYSTEM) != 0;
                
                String appName = packageManager.getApplicationLabel(appInfo).toString();
                String packageName = appInfo.packageName;
                
                long installTime = 0;
                long updateTime = 0;
                try {
                    PackageInfo packageInfo = packageManager.getPackageInfo(packageName, 0);
                    installTime = packageInfo.firstInstallTime;
                    updateTime = packageInfo.lastUpdateTime;
                } catch (PackageManager.NameNotFoundException e) {
                    Log.w(TAG, "Could not get package info for " + packageName);
                }
                
                Map<String, Object> app = new HashMap<>();
                app.put("package_name", packageName);
                app.put("app_name", appName);
                app.put("is_system", isSystemApp);
                app.put("install_time", installTime);
                app.put("update_time", updateTime);
                
                installedApps.add(app);
            }
            
            Log.d(TAG, "Retrieved " + installedApps.size() + " installed apps");
            
        } catch (Exception e) {
            Log.e(TAG, "Error getting installed apps", e);
        }
        
        return installedApps;
    }
    
    /**
     * Sync app usage data to database
     */
    public void syncAppUsageToDatabase(String userId, int days) {
        try {
            List<Map<String, Object>> appStats = getAppUsageStats(days);
            
            for (Map<String, Object> stat : appStats) {
                String packageName = (String) stat.get("package_name");
                String appName = (String) stat.get("app_name");
                long usageTime = (long) stat.get("usage_time");
                
                dbManager.insertOrUpdateAppUsage(userId, packageName, appName, usageTime);
            }
            
            Log.d(TAG, "Synced " + appStats.size() + " app usage records to database");
            
        } catch (Exception e) {
            Log.e(TAG, "Error syncing app usage to database", e);
        }
    }
    
    /**
     * Sync installed apps to database
     */
    public void syncInstalledAppsToDatabase(String userId) {
        try {
            List<Map<String, Object>> installedApps = getInstalledApps();
            
            for (Map<String, Object> app : installedApps) {
                String packageName = (String) app.get("package_name");
                String appName = (String) app.get("app_name");
                
                // Store as 0 usage time for newly discovered apps
                dbManager.insertOrUpdateAppUsage(userId, packageName, appName, 0);
            }
            
            Log.d(TAG, "Synced " + installedApps.size() + " installed apps to database");
            
        } catch (Exception e) {
            Log.e(TAG, "Error syncing installed apps to database", e);
        }
    }
    
    /**
     * Get app name from package name
     */
    private String getAppName(String packageName) {
        try {
            ApplicationInfo appInfo = packageManager.getApplicationInfo(packageName, 0);
            return packageManager.getApplicationLabel(appInfo).toString();
        } catch (PackageManager.NameNotFoundException e) {
            return packageName;
        }
    }
    
    /**
     * Check if PACKAGE_USAGE_STATS permission is granted
     */
    private boolean hasUsageStatsPermission() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.LOLLIPOP) {
            return true;
        }
        
        try {
            AppOpsManager appOps = (AppOpsManager) context.getSystemService(Context.APP_OPS_SERVICE);
            if (appOps == null) return false;
            
            int mode = appOps.checkOpNoThrow(AppOpsManager.OPSTR_GET_USAGE_STATS,
                    android.os.Process.myUid(), context.getPackageName());
            return mode == AppOpsManager.MODE_ALLOWED;
        } catch (Exception e) {
            Log.e(TAG, "Error checking usage stats permission", e);
            return false;
        }
    }
}
