package com.mr1aminul.civicmanagement;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.media.AudioManager;
import android.os.Build;
import android.os.PowerManager;
import android.util.Log;

public class AlarmReceiver extends BroadcastReceiver {
    private static final String TAG = "AlarmReceiver";
    private static final String PREFS_NAME = "AlarmPrefs";
    private static final String KEY_ACTIVE_ALARM_ID = "active_alarm_id";

    @Override
    public void onReceive(Context context, Intent intent) {
        if (intent == null) {
            Log.e(TAG, "Received null intent");
            return;
        }

        String alarmId = intent.getStringExtra("alarm_id");
        String title = intent.getStringExtra("title");
        String description = intent.getStringExtra("description");
        String urlLink = intent.getStringExtra("url");

        Log.d(TAG, "Alarm received: " + alarmId);

        // Check if this alarm is already being displayed
        SharedPreferences prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE);
        String activeAlarmId = prefs.getString(KEY_ACTIVE_ALARM_ID, null);

        if (alarmId != null && alarmId.equals(activeAlarmId)) {
            Log.d(TAG, "Alarm already active, ignoring: " + alarmId);
            return;
        }

        // Set this alarm as active
        prefs.edit().putString(KEY_ACTIVE_ALARM_ID, alarmId).apply();

        // Acquire wake lock
        PowerManager pm = (PowerManager) context.getSystemService(Context.POWER_SERVICE);
        PowerManager.WakeLock wakeLock = null;
        if (pm != null) {
            wakeLock = pm.newWakeLock(
                PowerManager.FULL_WAKE_LOCK | PowerManager.ACQUIRE_CAUSES_WAKEUP | PowerManager.ON_AFTER_RELEASE,
                "CivicManagement::AlarmWakelock"
            );
            wakeLock.acquire(10 * 60 * 1000L);
        }

        // Set high volume and bypass silent mode
        setHighVolumeAndBypassDND(context);

        // Start alarm activity directly - simpler and more reliable
        Intent alarmIntent = new Intent(context, AlarmActivity.class);
        alarmIntent.putExtra("alarm_id", alarmId);
        alarmIntent.putExtra("title", title);
        alarmIntent.putExtra("description", description);
        alarmIntent.putExtra("url", urlLink);
        alarmIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
        context.startActivity(alarmIntent);

        Log.d(TAG, "Started AlarmActivity for: " + alarmId);

        // Release wake lock
        if (wakeLock != null && wakeLock.isHeld()) {
            wakeLock.release();
        }
    }

    private void setHighVolumeAndBypassDND(Context context) {
        try {
            AudioManager audioManager = (AudioManager) context.getSystemService(Context.AUDIO_SERVICE);
            
            if (audioManager != null) {
                // Save current ringer mode
                int currentRingerMode = audioManager.getRingerMode();
                
                // Set to normal mode if in silent
                if (currentRingerMode == AudioManager.RINGER_MODE_SILENT) {
                    audioManager.setRingerMode(AudioManager.RINGER_MODE_NORMAL);
                }
                
                // Set alarm volume to maximum
                int maxVolume = audioManager.getStreamMaxVolume(AudioManager.STREAM_ALARM);
                audioManager.setStreamVolume(AudioManager.STREAM_ALARM, maxVolume, 0);
            }
        } catch (Exception e) {
            Log.e(TAG, "Error setting volume", e);
        }
    }
}
