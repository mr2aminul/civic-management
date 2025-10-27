package com.mr1aminul.civicmanagement;

import android.app.AlarmManager;
import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.location.Location;
import android.os.Build;
import android.os.Handler;
import android.os.IBinder;
import android.os.Looper;
import android.os.PowerManager;
import android.util.Log;

import androidx.annotation.Nullable;
import androidx.core.app.NotificationCompat;

import com.google.android.gms.location.FusedLocationProviderClient;
import com.google.android.gms.location.LocationCallback;
import com.google.android.gms.location.LocationRequest;
import com.google.android.gms.location.LocationResult;
import com.google.android.gms.location.LocationServices;
import com.google.android.gms.location.Priority;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.HashMap;
import java.util.HashSet;
import java.util.Set;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;

public class EnhancedBackgroundService extends Service {
    private static final String TAG = "EnhancedBackgroundService";
    private static final int NOTIFICATION_ID = 1001;
    private static final String CHANNEL_ID = "BackgroundServiceChannel";
    
    private static final String PREFS_NAME = "app_prefs";
    private static final String PREF_USER_ID = "user_id2";
    
    private FusedLocationProviderClient fusedLocationClient;
    private LocationCallback locationCallback;
    private Location currentLocation;
    
    private ScheduledExecutorService scheduler;
    private Handler mainHandler;
    private PowerManager.WakeLock wakeLock;
    
    private HashMap<String, PendingIntent> scheduledAlarms = new HashMap<>();
    private Set<String> processedAlarms = new HashSet<>();
    private Set<String> processedNotifications = new HashSet<>();
    
    private String userId;
    private boolean isServiceRunning = false;

    @Override
    public void onCreate() {
        super.onCreate();
        Log.d(TAG, "Service created");
        
        // MUST call startForeground immediately in onCreate for foreground services
        createNotificationChannel();
        startForeground(NOTIFICATION_ID, createNotification());
        
        acquireWakeLock();
        
        SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        userId = prefs.getString(PREF_USER_ID, null);
        
        mainHandler = new Handler(Looper.getMainLooper());
        scheduler = Executors.newScheduledThreadPool(3);
        
        setupLocationTracking();
        startPeriodicTasks();
        
        isServiceRunning = true;
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        Log.d(TAG, "Service started");
        
        if (!isServiceRunning) {
            // Service was restarted, reinitialize
            onCreate();
        }
        
        return START_STICKY;
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                CHANNEL_ID,
                "App Services",
                NotificationManager.IMPORTANCE_MIN  // Minimal importance - barely visible
            );
            channel.setDescription("Application background services");
            channel.setShowBadge(false);
            channel.setSound(null, null);  // No sound
            channel.enableVibration(false);  // No vibration
            channel.setLockscreenVisibility(Notification.VISIBILITY_SECRET);  // Hidden on lock screen
            
            NotificationManager manager = getSystemService(NotificationManager.class);
            if (manager != null) {
                manager.createNotificationChannel(channel);
            }
        }
    }

    private Notification createNotification() {
        return new NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("Civic Management")
            .setContentText("App is running")
            .setSmallIcon(R.mipmap.ic_launcher)
            .setPriority(NotificationCompat.PRIORITY_MIN)  // Lowest priority
            .setOngoing(true)
            .setShowWhen(false)
            .setSilent(true)  // Silent notification
            .setVisibility(NotificationCompat.VISIBILITY_SECRET)  // Hidden on lock screen
            .build();
    }

    private void acquireWakeLock() {
        try {
            PowerManager powerManager = (PowerManager) getSystemService(Context.POWER_SERVICE);
            if (powerManager != null) {
                wakeLock = powerManager.newWakeLock(
                    PowerManager.PARTIAL_WAKE_LOCK,
                    "CivicManagement::BackgroundWakeLock"
                );
                wakeLock.acquire(10 * 60 * 1000L);
            }
        } catch (Exception e) {
            Log.e(TAG, "Error acquiring wake lock", e);
        }
    }

    private void setupLocationTracking() {
        try {
            fusedLocationClient = LocationServices.getFusedLocationProviderClient(this);
            
            LocationRequest locationRequest = new LocationRequest.Builder(
                Priority.PRIORITY_HIGH_ACCURACY, 
                Config.LOCATION_SYNC_INTERVAL
            )
            .setMinUpdateIntervalMillis(Config.LOCATION_SYNC_INTERVAL / 2)
            .setMaxUpdateDelayMillis(Config.LOCATION_SYNC_INTERVAL * 2)
            .build();
            
            locationCallback = new LocationCallback() {
                @Override
                public void onLocationResult(LocationResult locationResult) {
                    if (locationResult == null) return;
                    currentLocation = locationResult.getLastLocation();
                    // Removed location logging for privacy
                }
            };
            
            fusedLocationClient.requestLocationUpdates(locationRequest, locationCallback, Looper.getMainLooper());
            Log.d(TAG, "Location tracking setup completed");
        } catch (SecurityException e) {
            Log.e(TAG, "Location permission not granted", e);
        } catch (Exception e) {
            Log.e(TAG, "Error setting up location tracking", e);
        }
    }

    private void startPeriodicTasks() {
        try {
            // Location sync task
            scheduler.scheduleWithFixedDelay(() -> {
                if (currentLocation != null && userId != null) {
                    sendLocationToServer(currentLocation);
                }
            }, 0, Config.LOCATION_SYNC_INTERVAL / 1000, TimeUnit.SECONDS);
            
            // Data sync task
            scheduler.scheduleWithFixedDelay(() -> {
                if (userId != null) {
                    fetchAndProcessServerData();
                }
            }, 0, Config.SYNC_INTERVAL / 1000, TimeUnit.SECONDS);
            
            // Service health check
            scheduler.scheduleWithFixedDelay(this::performHealthCheck, 60, 60, TimeUnit.SECONDS);
            
            Log.d(TAG, "Periodic tasks started");
        } catch (Exception e) {
            Log.e(TAG, "Error starting periodic tasks", e);
        }
    }

    private void sendLocationToServer(Location location) {
        scheduler.execute(() -> {
            try {
                String appVersion = getAppVersion();
                URL url = new URL(Config.LOCATION_UPDATE_ENDPOINT + "?app_version=" + appVersion);
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setConnectTimeout(10000);
                conn.setReadTimeout(10000);
                conn.setDoOutput(true);
                conn.setRequestProperty("Content-Type", "application/json");
                
                JSONObject jsonParam = new JSONObject();
                jsonParam.put("user_id", userId);
                jsonParam.put("latitude", location.getLatitude());
                jsonParam.put("longitude", location.getLongitude());
                jsonParam.put("accuracy", location.getAccuracy());
                jsonParam.put("timestamp", System.currentTimeMillis());
                
                OutputStream os = conn.getOutputStream();
                os.write(jsonParam.toString().getBytes("UTF-8"));
                os.close();
                
                conn.disconnect();
            } catch (Exception e) {
                // Silent error handling
            }
        });
    }
    private void fetchAndProcessServerData() {
        scheduler.execute(() -> {
            try {
                Log.d(TAG, "Starting fetchAndProcessServerData...");

                String appVersion = getAppVersion();
                Log.d(TAG, "App version: " + appVersion);

                URL url = new URL(Config.SERVER_BASE_URL + "combined_data.php?user_id=" + userId + "&app_version=" + appVersion);
                Log.d(TAG, "Constructed URL: " + url.toString());

                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setConnectTimeout(10000);
                conn.setReadTimeout(10000);
                conn.setRequestMethod("GET");

                Log.d(TAG, "Sending request...");
                int responseCode = conn.getResponseCode();
                Log.d(TAG, "Response code: " + responseCode);

                if (responseCode == 200) {
                    BufferedReader in = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                    StringBuilder response = new StringBuilder();
                    String inputLine;
                    while ((inputLine = in.readLine()) != null) {
                        response.append(inputLine);
                    }
                    in.close();

                    String responseStr = response.toString();
                    Log.d(TAG, "Server response length: " + responseStr.length());

                    // Parse the response - it seems to have debug output before JSON
                    int jsonStart = responseStr.indexOf("{");
                    Log.d(TAG, "JSON start index: " + jsonStart);

                    if (jsonStart != -1) {
                        String jsonStr = responseStr.substring(jsonStart);
                        Log.d(TAG, "JSON string extracted");

                        JSONObject json = new JSONObject(jsonStr);
                        String status = json.optString("status");
                        Log.d(TAG, "Response status: " + status);

                        if ("success".equalsIgnoreCase(status)) {
                            Log.d(TAG, "Processing debug notifications...");
                            processDebugNotifications(responseStr);

                            JSONArray notifications = json.optJSONArray("notifications");
                            Log.d(TAG, "Notifications count: " + (notifications != null ? notifications.length() : 0));
                            if (notifications != null) {
                                processNotifications(notifications);
                            }

                            JSONArray alarms = json.optJSONArray("alarms");
                            Log.d(TAG, "Alarms count: " + (alarms != null ? alarms.length() : 0));
                            if (alarms != null) {
                                processAlarmsWithDeduplication(alarms);
                            }
                        } else {
                            Log.d(TAG, "Status not success, skipping processing.");
                        }
                    } else {
                        Log.w(TAG, "No JSON found in server response.");
                    }
                } else {
                    Log.w(TAG, "Server returned non-200 response.");
                }
                conn.disconnect();
                Log.d(TAG, "Connection closed.");
            } catch (Exception e) {
                Log.e(TAG, "Error fetching server data", e);
            }
        });
    }


    private void processDebugNotifications(String responseStr) {
        try {
            // Extract notifications from the debug Array output
            if (responseStr.contains("Array") && responseStr.contains("[id]")) {
                String[] lines = responseStr.split("\n");
                String currentId = null;
                String currentTitle = null;
                String currentDescription = null;
                String currentUrl = null;
                
                for (String line : lines) {
                    line = line.trim();
                    if (line.contains("[id] =>")) {
                        currentId = line.replaceAll(".*\\[id\\]\\s*=>\\s*(\\d+).*", "$1");
                    } else if (line.contains("[title] =>")) {
                        currentTitle = line.replaceAll(".*\\[title\\]\\s*=>\\s*(.+)", "$1").trim();
                    } else if (line.contains("[description] =>")) {
                        currentDescription = line.replaceAll(".*\\[description\\]\\s*=>\\s*(.+)", "$1").trim();
                    } else if (line.contains("[url] =>")) {
                        currentUrl = line.replaceAll(".*\\[url\\]\\s*=>\\s*(.+)", "$1").trim();
                        
                        // Process notification when we have all data
                        if (currentId != null && currentTitle != null && !processedNotifications.contains(currentId)) {
                            Log.d(TAG, "Processing debug notification: " + currentId + " - " + currentTitle);
                            
                            // Check if it's a lead notification
                            if (currentUrl != null && currentUrl.contains("lead_id=")) {
                                String leadId = extractLeadId(currentUrl);
                                if (leadId != null) {
                                    showFloatingLeadNotification(leadId, currentTitle, currentDescription);
                                }
                            } else {
                                showNotification(currentId, currentTitle, currentDescription, currentUrl);
                            }
                            
                            processedNotifications.add(currentId);
                        }
                        
                        // Reset for next notification
                        currentId = null;
                        currentTitle = null;
                        currentDescription = null;
                        currentUrl = null;
                    }
                }
            }
        } catch (Exception e) {
            Log.e(TAG, "Error processing debug notifications", e);
        }
    }

    private String extractLeadId(String url) {
        try {
            if (url.contains("lead_id=")) {
                String[] parts = url.split("lead_id=");
                if (parts.length > 1) {
                    String leadPart = parts[1];
                    String[] leadParts = leadPart.split("&");
                    return leadParts[0];
                }
            }
        } catch (Exception e) {
            Log.e(TAG, "Error extracting lead ID", e);
        }
        return null;
    }

    private void processNotifications(JSONArray notifications) {
        try {
            for (int i = 0; i < notifications.length(); i++) {
                JSONObject notif = notifications.getJSONObject(i);
                String notifId = notif.getString("id");
                
                if (!processedNotifications.contains(notifId)) {
                    String title = notif.optString("title", "Notification");
                    String description = notif.optString("description", "");
                    String urlLink = notif.optString("url", Config.DEFAULT_URL);
                    String type = notif.optString("type", "normal");
                    String leadId = notif.optString("lead_id", "");
                    
                    Log.d(TAG, "Processing JSON notification: " + notifId + " - " + title);
                    
                    // Show floating notification for lead notifications
                    if ("lead".equals(type) && !leadId.isEmpty()) {
                        showFloatingLeadNotification(leadId, title, description);
                    } else if (urlLink.contains("lead_id=")) {
                        String extractedLeadId = extractLeadId(urlLink);
                        if (extractedLeadId != null) {
                            showFloatingLeadNotification(extractedLeadId, title, description);
                        }
                    } else {
                        showNotification(notifId, title, description, urlLink);
                    }
                    
                    processedNotifications.add(notifId);
                }
            }
        } catch (Exception e) {
            Log.e(TAG, "Error processing JSON notifications", e);
        }
    }

    private void showFloatingLeadNotification(String leadId, String title, String description) {
        try {
            Log.d(TAG, "Showing floating lead notification for lead: " + leadId);
            Intent intent = new Intent(this, FloatingNotificationService.class);
            intent.putExtra("action", "SHOW_LEAD_NOTIFICATION");
            intent.putExtra("lead_id", leadId);
            intent.putExtra("title", title);
            intent.putExtra("message", description);
            startService(intent);
        } catch (Exception e) {
            Log.e(TAG, "Error showing floating lead notification", e);
        }
    }

    private void processAlarmsWithDeduplication(JSONArray alarms) {
        try {
            Set<String> currentAlarmIds = new HashSet<>();
            
            for (int i = 0; i < alarms.length(); i++) {
                JSONObject alarm = alarms.getJSONObject(i);
                String alarmId = alarm.getString("id");
                currentAlarmIds.add(alarmId);
                
                String status = alarm.optString("status", "active");
                
                if ("deleted".equalsIgnoreCase(status)) {
                    cancelAlarm(alarmId);
                    processedAlarms.remove(alarmId);
                } else if (!processedAlarms.contains(alarmId)) {
                    long triggerTime = alarm.getLong("time");
                    String title = alarm.optString("title", "Alarm");
                    String description = alarm.optString("description", "");
                    String urlLink = alarm.optString("url", Config.DEFAULT_URL);
                    
                    Log.d(TAG, "Scheduling alarm: " + alarmId + " - " + title);
                    scheduleAlarm(alarmId, triggerTime, title, description, urlLink);
                    processedAlarms.add(alarmId);
                }
            }
            
            Set<String> toRemove = new HashSet<>();
            for (String processedId : processedAlarms) {
                if (!currentAlarmIds.contains(processedId)) {
                    cancelAlarm(processedId);
                    toRemove.add(processedId);
                }
            }
            processedAlarms.removeAll(toRemove);
            
        } catch (Exception e) {
            Log.e(TAG, "Error processing alarms", e);
        }
    }

    private void scheduleAlarm(String alarmId, long triggerTime, String title, String description, String url) {
        try {
            Intent intent = new Intent(this, AlarmReceiver.class);
            intent.putExtra("alarm_id", alarmId);
            intent.putExtra("title", title);
            intent.putExtra("description", description);
            intent.putExtra("url", url);
            
            PendingIntent pendingIntent = PendingIntent.getBroadcast(
                this,
                alarmId.hashCode(),
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
            );
            
            AlarmManager alarmManager = (AlarmManager) getSystemService(Context.ALARM_SERVICE);
            if (alarmManager != null) {
                long currentTime = System.currentTimeMillis();
                if (triggerTime <= currentTime) {
                    triggerTime = currentTime + 5000;
                }
                
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                    alarmManager.setExactAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, triggerTime, pendingIntent);
                } else {
                    alarmManager.setExact(AlarmManager.RTC_WAKEUP, triggerTime, pendingIntent);
                }
                
                scheduledAlarms.put(alarmId, pendingIntent);
                Log.d(TAG, "Alarm scheduled: " + alarmId + " at " + new java.util.Date(triggerTime));
            }
        } catch (Exception e) {
            Log.e(TAG, "Error scheduling alarm", e);
        }
    }

    private void cancelAlarm(String alarmId) {
        try {
            PendingIntent pendingIntent = scheduledAlarms.get(alarmId);
            if (pendingIntent != null) {
                AlarmManager alarmManager = (AlarmManager) getSystemService(Context.ALARM_SERVICE);
                if (alarmManager != null) {
                    alarmManager.cancel(pendingIntent);
                }
                scheduledAlarms.remove(alarmId);
                Log.d(TAG, "Alarm cancelled: " + alarmId);
            }
        } catch (Exception e) {
            Log.e(TAG, "Error cancelling alarm", e);
        }
    }

    private void showNotification(String notifId, String title, String description, String url) {
        try {
            Intent intent = new Intent(this, MainActivity.class);
            intent.putExtra("url", url);
            intent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
            
            PendingIntent pendingIntent = PendingIntent.getActivity(
                this,
                notifId.hashCode(),
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
            );
            
            NotificationCompat.Builder builder = new NotificationCompat.Builder(this, CHANNEL_ID)
                .setSmallIcon(R.mipmap.ic_launcher)
                .setContentTitle(title)
                .setContentText(description)
                .setContentIntent(pendingIntent)
                .setAutoCancel(true)
                .setPriority(NotificationCompat.PRIORITY_HIGH);
            
            NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
            if (manager != null) {
                manager.notify(notifId.hashCode(), builder.build());
                Log.d(TAG, "Notification shown: " + notifId + " - " + title);
            }
        } catch (Exception e) {
            Log.e(TAG, "Error showing notification", e);
        }
    }

    private void performHealthCheck() {
        try {
            // Refresh wake lock
            if (wakeLock != null && wakeLock.isHeld()) {
                wakeLock.release();
            }
            acquireWakeLock();
            
            // Update notification
            NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
            if (manager != null) {
                manager.notify(NOTIFICATION_ID, createNotification());
            }
        } catch (Exception e) {
            Log.e(TAG, "Error in health check", e);
        }
    }

    private String getAppVersion() {
        try {
            return getPackageManager().getPackageInfo(getPackageName(), 0).versionName;
        } catch (Exception e) {
            return "unknown";
        }
    }

    @Override
    public void onTaskRemoved(Intent rootIntent) {
        Intent restartIntent = new Intent(this, ServiceRestartReceiver.class);
        PendingIntent pendingIntent = PendingIntent.getBroadcast(
            this, 
            1, 
            restartIntent, 
            PendingIntent.FLAG_ONE_SHOT | PendingIntent.FLAG_IMMUTABLE
        );
        
        AlarmManager alarmManager = (AlarmManager) getSystemService(Context.ALARM_SERVICE);
        if (alarmManager != null) {
            alarmManager.set(AlarmManager.RTC, System.currentTimeMillis() + 1000, pendingIntent);
        }
        
        super.onTaskRemoved(rootIntent);
    }

    @Override
    public void onDestroy() {
        Log.d(TAG, "Service destroyed");
        isServiceRunning = false;
        
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
        
        if (fusedLocationClient != null && locationCallback != null) {
            fusedLocationClient.removeLocationUpdates(locationCallback);
        }
        
        if (wakeLock != null && wakeLock.isHeld()) {
            wakeLock.release();
        }
        
        super.onDestroy();
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }
}
