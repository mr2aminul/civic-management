package com.mr1aminul.civicmanagement;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.os.Build;
import android.util.Log;

public class ServiceRestartReceiver extends BroadcastReceiver {
    private static final String TAG = "ServiceRestartReceiver";

    @Override
    public void onReceive(Context context, Intent intent) {
        Log.d(TAG, "Restarting services");
        
        try {
            Intent backgroundIntent = new Intent(context, EnhancedBackgroundService.class);
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.startForegroundService(backgroundIntent);
            } else {
                context.startService(backgroundIntent);
            }

            Intent callIntent = new Intent(context, CallTrackingService.class);
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.startForegroundService(callIntent);
            } else {
                context.startService(callIntent);
            }
        } catch (Exception e) {
            Log.e(TAG, "Error restarting services", e);
        }
    }
}
