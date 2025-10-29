package com.mr1aminul.civicmanagement;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.content.pm.PackageManager;
import android.os.Build;
import android.util.Log;

import com.mr1aminul.civicmanagement.database.DatabaseManager;

/**
 * Tracks app installations and uninstallations
 */
public class AppInstallationTracker extends BroadcastReceiver {
    private static final String TAG = "AppInstallationTracker";
    private static AppInstallationTracker instance;
    private Context context;
    private DatabaseManager dbManager;
    
    private AppInstallationTracker(Context context) {
        this.context = context.getApplicationContext();
        this.dbManager = DatabaseManager.getInstance(context);
    }
    
    public static synchronized AppInstallationTracker getInstance(Context context) {
        if (instance == null) {
            instance = new AppInstallationTracker(context);
        }
        return instance;
    }
    
    /**
     * Register broadcast receiver for app installation/uninstallation events
     */
    public void registerReceiver() {
        try {
            IntentFilter filter = new IntentFilter();
            filter.addAction(Intent.ACTION_PACKAGE_ADDED);
            filter.addAction(Intent.ACTION_PACKAGE_REMOVED);
            filter.addAction(Intent.ACTION_PACKAGE_REPLACED);
            filter.addDataScheme("package");
            
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.registerReceiver(this, filter, Context.RECEIVER_EXPORTED);
            } else {
                context.registerReceiver(this, filter);
            }
            
            Log.d(TAG, "App installation tracker registered");
        } catch (Exception e) {
            Log.e(TAG, "Error registering app installation tracker", e);
        }
    }
    
    /**
     * Unregister broadcast receiver
     */
    public void unregisterReceiver() {
        try {
            context.unregisterReceiver(this);
            Log.d(TAG, "App installation tracker unregistered");
        } catch (Exception e) {
            Log.e(TAG, "Error unregistering app installation tracker", e);
        }
    }
    
    @Override
    public void onReceive(Context context, Intent intent) {
        if (intent == null) return;
        
        String action = intent.getAction();
        String packageName = intent.getData().getSchemeSpecificPart();
        
        try {
            if (Intent.ACTION_PACKAGE_ADDED.equals(action)) {
                handleAppInstalled(packageName);
            } else if (Intent.ACTION_PACKAGE_REMOVED.equals(action)) {
                handleAppUninstalled(packageName);
            } else if (Intent.ACTION_PACKAGE_REPLACED.equals(action)) {
                handleAppUpdated(packageName);
            }
        } catch (Exception e) {
            Log.e(TAG, "Error handling app event", e);
        }
    }
    
    private void handleAppInstalled(String packageName) {
        try {
            PackageManager pm = context.getPackageManager();
            String appName = pm.getApplicationLabel(
                    pm.getApplicationInfo(packageName, 0)).toString();
            
            Log.d(TAG, "App installed: " + appName + " (" + packageName + ")");
            
            // Store in database
            // You can add this to a separate table for tracking installations
            
        } catch (Exception e) {
            Log.e(TAG, "Error handling app installation", e);
        }
    }
    
    private void handleAppUninstalled(String packageName) {
        Log.d(TAG, "App uninstalled: " + packageName);
        // Handle app uninstallation
    }
    
    private void handleAppUpdated(String packageName) {
        try {
            PackageManager pm = context.getPackageManager();
            String appName = pm.getApplicationLabel(
                    pm.getApplicationInfo(packageName, 0)).toString();
            
            Log.d(TAG, "App updated: " + appName + " (" + packageName + ")");
            
        } catch (Exception e) {
            Log.e(TAG, "Error handling app update", e);
        }
    }
}
