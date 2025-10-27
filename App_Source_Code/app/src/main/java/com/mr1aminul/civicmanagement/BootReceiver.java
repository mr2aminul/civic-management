package com.mr1aminul.civicmanagement;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.os.Build;
import android.util.Log;

public class BootReceiver extends BroadcastReceiver {
    private static final String TAG = "BootReceiver";

    @Override
    public void onReceive(Context context, Intent intent) {
        if (intent == null || intent.getAction() == null) {
            return;
        }

        Log.d(TAG, "Boot receiver triggered: " + intent.getAction());

        String action = intent.getAction();
        if (Intent.ACTION_BOOT_COMPLETED.equals(action) ||
            Intent.ACTION_MY_PACKAGE_REPLACED.equals(action) ||
            "android.intent.action.QUICKBOOT_POWERON".equals(action) ||
            "com.htc.intent.action.QUICKBOOT_POWERON".equals(action)) {
            
            startServices(context);
        }
    }

    private void startServices(Context context) {
        try {
            // Start enhanced background service
            Intent backgroundIntent = new Intent(context, EnhancedBackgroundService.class);
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.startForegroundService(backgroundIntent);
            } else {
                context.startService(backgroundIntent);
            }

            // Start call tracking service
            Intent callIntent = new Intent(context, CallTrackingService.class);
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.startForegroundService(callIntent);
            } else {
                context.startService(callIntent);
            }

            Log.d(TAG, "Services started after boot");
        } catch (Exception e) {
            Log.e(TAG, "Error starting services", e);
        }
    }
}
