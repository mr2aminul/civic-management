package com.mr1aminul.civicmanagement;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.database.Cursor;
import android.os.Build;
import android.os.Handler;
import android.os.IBinder;
import android.os.Looper;
import android.provider.CallLog;
import android.telephony.PhoneStateListener;
import android.telephony.TelephonyManager;
import android.util.Log;

import androidx.annotation.Nullable;
import androidx.core.app.NotificationCompat;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.HashSet;
import java.util.Locale;
import java.util.Set;
import java.util.TimeZone;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;

public class CallTrackingService extends Service {
    private static final String TAG = "CallTrackingService";
    private static final int NOTIFICATION_ID = 3001;
    private static final String CHANNEL_ID = "CallTrackingChannel";
    private static final String PREFS_NAME = "app_prefs";
    private static final String PREF_USER_ID = "user_id2";
    private static final String PREF_UPLOADED_CALLS = "uploaded_calls_set";
    
    private TelephonyManager telephonyManager;
    private CallStateListener callStateListener;
    private ScheduledExecutorService scheduler;
    private String userId;

    @Override
    public void onCreate() {
        super.onCreate();
        Log.d(TAG, "Service created");
        
        // MUST call startForeground immediately in onCreate for foreground services
        createNotificationChannel();
        startForeground(NOTIFICATION_ID, createNotification());
        
        SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        userId = prefs.getString(PREF_USER_ID, null);
        
        if (userId == null) {
            Log.e(TAG, "No user ID found, stopping service");
            stopSelf();
            return;
        }
        
        setupCallTracking();
        startBatchUploader();
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        Log.d(TAG, "Service started");
        
        // Ensure we're running as foreground service
        if (userId == null) {
            stopSelf();
            return START_NOT_STICKY;
        }
        
        return START_STICKY;
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                CHANNEL_ID,
                "Business Monitoring",
                NotificationManager.IMPORTANCE_MIN  // Minimal importance
            );
            channel.setDescription("Employee productivity monitoring");
            channel.setShowBadge(false);
            channel.setSound(null, null);
            channel.enableVibration(false);
            channel.setLockscreenVisibility(Notification.VISIBILITY_SECRET);
            
            NotificationManager manager = getSystemService(NotificationManager.class);
            if (manager != null) {
                manager.createNotificationChannel(channel);
            }
        }
    }

    private Notification createNotification() {
        return new NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("Business Monitoring")
            .setContentText("Employee productivity tracking active")
            .setSmallIcon(R.mipmap.ic_launcher)
            .setPriority(NotificationCompat.PRIORITY_MIN)
            .setOngoing(true)
            .setShowWhen(false)
            .setSilent(true)
            .setVisibility(NotificationCompat.VISIBILITY_SECRET)
            .build();
    }

    private void setupCallTracking() {
        try {
            telephonyManager = (TelephonyManager) getSystemService(Context.TELEPHONY_SERVICE);
            callStateListener = new CallStateListener();
            
            if (telephonyManager != null) {
                telephonyManager.listen(callStateListener, PhoneStateListener.LISTEN_CALL_STATE);
                Log.d(TAG, "Call tracking setup completed");
            }
        } catch (SecurityException e) {
            Log.e(TAG, "Security exception in call tracking setup", e);
        } catch (Exception e) {
            Log.e(TAG, "Error setting up call tracking", e);
        }
    }

    private void startBatchUploader() {
        try {
            scheduler = Executors.newScheduledThreadPool(1);
            scheduler.scheduleWithFixedDelay(this::uploadCallLogs, 0, 60, TimeUnit.SECONDS);
            Log.d(TAG, "Batch uploader started");
        } catch (Exception e) {
            Log.e(TAG, "Error starting batch uploader", e);
        }
    }

    private class CallStateListener extends PhoneStateListener {
        private boolean inCall = false;

        @Override
        public void onCallStateChanged(int state, String phoneNumber) {
            try {
                switch (state) {
                    case TelephonyManager.CALL_STATE_OFFHOOK:
                        inCall = true;
                        Log.d(TAG, "Call started");
                        break;
                    case TelephonyManager.CALL_STATE_IDLE:
                        if (inCall) {
                            inCall = false;
                            Log.d(TAG, "Call ended, scheduling upload");
                            new Handler(Looper.getMainLooper()).postDelayed(this::uploadCallLogs, 3000);
                        }
                        break;
                    case TelephonyManager.CALL_STATE_RINGING:
                        Log.d(TAG, "Phone ringing");
                        break;
                }
            } catch (Exception e) {
                Log.e(TAG, "Error in call state change", e);
            }
        }

        private void uploadCallLogs() {
            CallTrackingService.this.uploadCallLogs();
        }
    }

    private void uploadCallLogs() {
        if (scheduler == null || scheduler.isShutdown()) {
            return;
        }
        
        scheduler.execute(() -> {
            try {
                Set<String> uploadedSet = getUploadedCallsSet();
                JSONArray newCalls = getNewCallLogs(uploadedSet);
                
                if (newCalls.length() > 0) {
                    boolean success = uploadToServer(newCalls);
                    if (success) {
                        for (int i = 0; i < newCalls.length(); i++) {
                            JSONObject call = newCalls.getJSONObject(i);
                            String uniqueKey = call.optString("_unique_key");
                            if (uniqueKey != null) {
                                uploadedSet.add(uniqueKey);
                            }
                        }
                        saveUploadedCallsSet(uploadedSet);
                        Log.d(TAG, "Uploaded " + newCalls.length() + " call records");
                    }
                }
            } catch (Exception e) {
                Log.e(TAG, "Error uploading call logs", e);
            }
        });
    }

    private JSONArray getNewCallLogs(Set<String> uploadedSet) {
        JSONArray newCalls = new JSONArray();
        
        Cursor cursor = null;
        try {
            // Simple query without LIMIT in any parameter
            cursor = getContentResolver().query(
                CallLog.Calls.CONTENT_URI,
                new String[]{
                    CallLog.Calls.NUMBER,
                    CallLog.Calls.DATE,
                    CallLog.Calls.TYPE,
                    CallLog.Calls.DURATION
                },
                null,  // selection
                null,  // selectionArgs
                CallLog.Calls.DATE + " DESC"  // sortOrder without LIMIT
            );
            
            if (cursor != null && cursor.moveToFirst()) {
                SimpleDateFormat sdf = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault());
                sdf.setTimeZone(TimeZone.getTimeZone("Asia/Dhaka"));
                
                int count = 0;
                do {
                    if (count >= 50) break; // Manual limit
                    
                    try {
                        int numberIndex = cursor.getColumnIndex(CallLog.Calls.NUMBER);
                        int dateIndex = cursor.getColumnIndex(CallLog.Calls.DATE);
                        int typeIndex = cursor.getColumnIndex(CallLog.Calls.TYPE);
                        int durationIndex = cursor.getColumnIndex(CallLog.Calls.DURATION);
                        
                        if (numberIndex == -1 || dateIndex == -1 || typeIndex == -1 || durationIndex == -1) {
                            continue;
                        }
                        
                        String number = cursor.getString(numberIndex);
                        long dateMs = cursor.getLong(dateIndex);
                        int type = cursor.getInt(typeIndex);
                        long duration = cursor.getLong(durationIndex);
                        
                        if (number == null || number.isEmpty()) {
                            continue;
                        }
                        
                        String uniqueKey = number + "_" + dateMs;
                        
                        if (!uploadedSet.contains(uniqueKey)) {
                            JSONObject call = new JSONObject();
                            call.put("user_id", userId);
                            call.put("number", number);
                            call.put("call_type", type == CallLog.Calls.OUTGOING_TYPE ? "outgoing" : "incoming");
                            call.put("status", duration > 0 ? "answered" : 
                                    (type == CallLog.Calls.MISSED_TYPE ? "missed_call" : "unreachable"));
                            call.put("duration", duration);
                            call.put("timestamp", sdf.format(new Date(dateMs)));
                            call.put("_unique_key", uniqueKey);
                            
                            newCalls.put(call);
                        }
                    } catch (Exception e) {
                        Log.e(TAG, "Error processing call log entry", e);
                    }
                    count++;
                } while (cursor.moveToNext());
            }
        } catch (Exception e) {
            Log.e(TAG, "Error querying call logs", e);
        } finally {
            if (cursor != null) {
                try {
                    cursor.close();
                } catch (Exception e) {
                    Log.e(TAG, "Error closing cursor", e);
                }
            }
        }
        
        return newCalls;
    }

    private boolean uploadToServer(JSONArray calls) {
        try {
            String appVersion = getPackageManager().getPackageInfo(getPackageName(), 0).versionName;
            String urlString = "https://civicgroupbd.com/app_api/calls_batch.php?app_version=" + 
                              URLEncoder.encode(appVersion, "UTF-8");
            
            URL url = new URL(urlString);
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("POST");
            conn.setDoOutput(true);
            conn.setConnectTimeout(10000);
            conn.setReadTimeout(10000);
            conn.setRequestProperty("Content-Type", "application/json; charset=UTF-8");
            
            OutputStream os = conn.getOutputStream();
            os.write(calls.toString().getBytes("UTF-8"));
            os.close();
            
            int responseCode = conn.getResponseCode();
            conn.disconnect();
            
            return responseCode >= 200 && responseCode < 300;
        } catch (Exception e) {
            Log.e(TAG, "Error uploading to server", e);
            return false;
        }
    }

    private Set<String> getUploadedCallsSet() {
        SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        return new HashSet<>(prefs.getStringSet(PREF_UPLOADED_CALLS, new HashSet<>()));
    }

    private void saveUploadedCallsSet(Set<String> set) {
        SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        prefs.edit().putStringSet(PREF_UPLOADED_CALLS, set).apply();
    }

    @Override
    public void onDestroy() {
        Log.d(TAG, "Service destroyed");
        
        if (telephonyManager != null && callStateListener != null) {
            try {
                telephonyManager.listen(callStateListener, PhoneStateListener.LISTEN_NONE);
            } catch (Exception e) {
                Log.e(TAG, "Error unregistering call listener", e);
            }
        }
        
        if (scheduler != null && !scheduler.isShutdown()) {
            scheduler.shutdown();
            try {
                if (!scheduler.awaitTermination(5, TimeUnit.SECONDS)) {
                    scheduler.shutdownNow();
                }
            } catch (InterruptedException e) {
                scheduler.shutdownNow();
                Thread.currentThread().interrupt();
            }
        }
        
        super.onDestroy();
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }
}
