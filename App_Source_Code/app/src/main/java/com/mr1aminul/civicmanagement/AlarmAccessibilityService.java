package com.mr1aminul.civicmanagement;

import android.accessibilityservice.AccessibilityService;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.util.Log;
import android.view.accessibility.AccessibilityEvent;

public class AlarmAccessibilityService extends AccessibilityService {
    private static final String TAG = "AlarmAccessibilityService";

    // Receiver to listen for the alarm trigger broadcast.
    private final BroadcastReceiver alarmReceiver = new BroadcastReceiver() {
        @Override
        public void onReceive(Context context, Intent intent) {
            // Retrieve alarm data from the broadcast.
            String alarmId = intent.getStringExtra("alarm_id");
            String title = intent.getStringExtra("title");
            String description = intent.getStringExtra("description");
            String urlLink = intent.getStringExtra("url");

            // Launch the AlarmActivity.
            Intent alarmIntent = new Intent(AlarmAccessibilityService.this, AlarmActivity.class);
            alarmIntent.putExtra("alarm_id", alarmId);
            alarmIntent.putExtra("title", title);
            alarmIntent.putExtra("description", description);
            alarmIntent.putExtra("url", urlLink);
            alarmIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            startActivity(alarmIntent);
            Log.d(TAG, "Launched AlarmActivity from AccessibilityService for alarm id: " + alarmId);
        }
    };

    @Override
    protected void onServiceConnected() {
        super.onServiceConnected();
        // Register the receiver for our custom alarm trigger.
        IntentFilter filter = new IntentFilter("com.mr1aminul.civicmanagement.ALARM_TRIGGER");
        registerReceiver(alarmReceiver, filter);
        Log.d(TAG, "AlarmAccessibilityService connected and receiver registered.");
    }

    @Override
    public void onAccessibilityEvent(AccessibilityEvent event) {
        // Optional: Log or process accessibility events.
        Log.d(TAG, "Accessibility Event received: " + event.toString());
    }

    @Override
    public void onInterrupt() {
        Log.d(TAG, "Accessibility Service interrupted.");
    }

    @Override
    public void onDestroy() {
        super.onDestroy();
        unregisterReceiver(alarmReceiver);
        Log.d(TAG, "AlarmAccessibilityService destroyed and receiver unregistered.");
    }
}
