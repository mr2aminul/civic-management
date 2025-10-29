package com.mr1aminul.civicmanagement;

import android.app.AlarmManager;
import android.app.DownloadManager;
import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.ActivityNotFoundException;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.content.SharedPreferences;
import android.content.pm.PackageInfo;
import android.content.pm.PackageManager;
import android.database.Cursor;
import android.location.Location;
import android.media.AudioManager;
import android.net.Uri;
import android.os.Build;
import android.os.Environment;
import android.os.Handler;
import android.os.IBinder;
import android.os.Looper;
import android.provider.Settings;
import android.util.Log;
import android.widget.Toast;

import androidx.annotation.Nullable;
import androidx.core.app.NotificationCompat;
import androidx.core.content.FileProvider;

import com.google.android.gms.location.FusedLocationProviderClient;
import com.google.android.gms.location.LocationCallback;
import com.google.android.gms.location.LocationRequest;
import com.google.android.gms.location.LocationResult;
import com.google.android.gms.location.LocationServices;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.File;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.ArrayList;
import java.util.HashMap;

public class BackgroundService extends Service {
    private static final String TAG = "BackgroundService";
    private static final int NOTIFICATION_ID = 101;
    private static final String CHANNEL_ID = "BackgroundServiceChannel";

    // Use a static flag to ensure that we only start the foreground service once.
    private static boolean isForegroundStarted = false;

    // App SharedPreferences keys (populated by MainActivity)
    private static final String PREFS_NAME = "app_prefs";
    private static final String PREF_USER_ID = "user_id2";

    // Config SharedPreferences keys for server config
    private static final String CONFIG_PREFS = "server_config_prefs";
    private static final String KEY_SYNC_INTERVAL = "SYNC_INTERVAL";
    private static final String KEY_LOCATION_SYNC_INTERVAL = "LOCATION_SYNC_INTERVAL";
    private static final String KEY_LOCATION_TRACKING_ENABLED = "LOCATION_TRACKING_ENABLED";
    private static final String KEY_NOTIFICATIONS_ENABLED = "NOTIFICATIONS_ENABLED";
    private static final String KEY_ALARMS_ENABLED = "ALARMS_ENABLED";
    private static final String KEY_CALL_TRACKING_ENABLED = "CALL_TRACKING_ENABLED";

    // Endpoints (ensure these are defined in your Config.java)
    private static final String LOCATION_ENDPOINT = Config.LOCATION_UPDATE_ENDPOINT;
//    private static final String NOTIFICATIONS_ENDPOINT = Config.NOTIFICATIONS_ENDPOINT;
    private static final String ALARMS_ENDPOINT = Config.ALARMS_ENDPOINT;

    private Handler handler;
    private FusedLocationProviderClient fusedLocationClient;
    private LocationCallback locationCallback;
    private Location currentLocation;
    private HashMap<String, PendingIntent> scheduledAlarms = new HashMap<>();
    private String userId = null;

    // Flag to prevent overlapping update processes
    private boolean isUpdateInProgress = false;

    // APK download retry configuration.
    private int apkDownloadRetries = 0;
    private static final int MAX_APK_DOWNLOAD_RETRIES = 3;
    private static final long RETRY_DELAY_MS = 60000; // 60 seconds

    @Override
    public void onCreate() {
        super.onCreate();
        createNotificationChannel();

        // Retrieve user_id from SharedPreferences.
        SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        userId = prefs.getString(PREF_USER_ID, null);
        if (userId == null || userId.isEmpty()) {
            stopSelf();
            return;
        }

        handler = new Handler(Looper.getMainLooper());

        // Start location updates if enabled.
        if (getBooleanConfig(KEY_LOCATION_TRACKING_ENABLED, Config.LOCATION_TRACKING_ENABLED)) {
            fusedLocationClient = LocationServices.getFusedLocationProviderClient(this);
            startLocationUpdates();
        }

        // Start call tracking if enabled.
        if (getBooleanConfig(KEY_CALL_TRACKING_ENABLED, Config.CALL_TRACKING_ENABLED)) {
            startCallTracking();
        }

        // Runnable for sending location updates.
        Runnable locationRunnable = new Runnable() {
            @Override
            public void run() {
                if (getBooleanConfig(KEY_LOCATION_TRACKING_ENABLED, Config.LOCATION_TRACKING_ENABLED)
                        && currentLocation != null && userId != null) {
                    sendLocationToServer(currentLocation);
                }
                long locationInterval = getLongConfig(KEY_LOCATION_SYNC_INTERVAL, Config.LOCATION_SYNC_INTERVAL);
                handler.postDelayed(this, locationInterval);
            }
        };

        // Runnable for processing combined data.
        Runnable combinedDataRunnable = new Runnable() {
            @Override
            public void run() {
                if (!isUpdateInProgress && userId != null &&
                        (getBooleanConfig(KEY_NOTIFICATIONS_ENABLED, Config.NOTIFICATIONS_ENABLED) ||
                                getBooleanConfig(KEY_ALARMS_ENABLED, Config.ALARMS_ENABLED))) {
                    checkCombinedData();
                }
                long syncInterval = getLongConfig(KEY_SYNC_INTERVAL, Config.SYNC_INTERVAL);
                handler.postDelayed(this, syncInterval);
            }
        };

        handler.post(locationRunnable);
        handler.post(combinedDataRunnable);
    }

    // Returns the app version.
    private String getAppVersion() {
        try {
            PackageManager packageManager = getApplicationContext().getPackageManager();
            PackageInfo packageInfo = packageManager.getPackageInfo(getApplicationContext().getPackageName(), 0);
            return packageInfo.versionName;
        } catch (PackageManager.NameNotFoundException e) {
            e.printStackTrace();
            return "unknown";
        }
    }

    // Check combined data from the server.
    private void checkCombinedData() {
        if (isUpdateInProgress) return;

        new Thread(() -> {
            try {
                String appVersion = getAppVersion();
                URL url = new URL(Config.COMBINED_DATA_ENDPOINT + "?user_id=" + userId + "&app_version=" + appVersion);
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setConnectTimeout(10000);
                conn.setReadTimeout(10000);
                conn.setRequestMethod("GET");

                if (conn.getResponseCode() == 200) {
                    BufferedReader in = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                    StringBuilder response = new StringBuilder();
                    String inputLine;
                    while ((inputLine = in.readLine()) != null) {
                        response.append(inputLine);
                    }
                    in.close();

                    JSONObject json = new JSONObject(response.toString());
                    if ("success".equalsIgnoreCase(json.optString("status"))) {
                        JSONArray notifications = json.optJSONArray("notifications");
                        if (notifications != null) {
                            processNotifications(notifications);
                        }
                        JSONArray alarms = json.optJSONArray("alarms");
                        if (alarms != null) {
                            processAlarms(alarms);
                        }
                        JSONObject serverConfig = json.optJSONObject("server_config");
                        if (serverConfig != null) {
                            updateLocalConfig(serverConfig);
                        }

                        // Process app update information.
                        String latestVersion = json.optString("latest_version", "");
                        String apkUrl = json.optString("apk_url", "");
                        String installedVersion = getAppVersion();

                        if (!latestVersion.isEmpty() && !apkUrl.isEmpty() && compareVersions(installedVersion, latestVersion) < 0) {
                            File apkFile = getApkFile(latestVersion);
                            if (apkFile.exists()) {
                                Log.d(TAG, "APK already downloaded: Re-triggering installation prompt...");
                                installApk(Uri.fromFile(apkFile));
                            } else {
                                downloadAndUpdateApk(apkUrl, latestVersion);
                            }
                        }
                    }
                }
                conn.disconnect();
            } catch (Exception e) {
                Log.e(TAG, "Error checking combined data", e);
            }
        }).start();
    }

    // Compares version numbers.
    private int compareVersions(String currentVersion, String latestVersion) {
        String normalizedCurrent = currentVersion.replaceAll("[^0-9.]", "");
        String normalizedLatest = latestVersion.replaceAll("[^0-9.]", "");
        String[] currentParts = normalizedCurrent.split("\\.");
        String[] latestParts = normalizedLatest.split("\\.");

        int length = Math.max(currentParts.length, latestParts.length);
        for (int i = 0; i < length; i++) {
            int currentPart = (i < currentParts.length ? Integer.parseInt(currentParts[i]) : 0);
            int latestPart = (i < latestParts.length ? Integer.parseInt(latestParts[i]) : 0);
            if (currentPart < latestPart) return -1;
            else if (currentPart > latestPart) return 1;
        }
        return 0;
    }

    // Returns a File in the app-specific downloads directory.
    private File getApkFile(String fileName) {
        File downloadDir = getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS);
        return new File(downloadDir, fileName);
    }

    // Downloads and updates the APK with a retry mechanism.
    private void downloadAndUpdateApk(String apkUrl, String apkFileName) {
        if (isUpdateInProgress) return;
        isUpdateInProgress = true;

        File apkFile = getApkFile(apkFileName);
        if (apkFile.exists()) {
            boolean deleted = apkFile.delete();
            Log.d(TAG, "Existing APK deleted: " + deleted);
        }

        DownloadManager.Request request = new DownloadManager.Request(Uri.parse(apkUrl));
        request.setTitle("Downloading update");
        request.setDescription("Downloading " + apkFileName);
        request.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
        request.setDestinationInExternalFilesDir(this, Environment.DIRECTORY_DOWNLOADS, apkFileName);

        DownloadManager downloadManager = (DownloadManager) getSystemService(DOWNLOAD_SERVICE);
        long downloadId = downloadManager.enqueue(request);

        BroadcastReceiver onComplete = new BroadcastReceiver() {
            @Override
            public void onReceive(Context ctx, Intent intent) {
                long id = intent.getLongExtra(DownloadManager.EXTRA_DOWNLOAD_ID, -1);
                if (id == downloadId) {
                    DownloadManager.Query query = new DownloadManager.Query();
                    query.setFilterById(downloadId);
                    Cursor cursor = downloadManager.query(query);
                    if (cursor != null && cursor.moveToFirst()) {
                        int status = cursor.getInt(cursor.getColumnIndex(DownloadManager.COLUMN_STATUS));
                        if (status == DownloadManager.STATUS_SUCCESSFUL) {
                            Uri downloadedUri = downloadManager.getUriForDownloadedFile(downloadId);
                            if (downloadedUri != null) {
                                Log.d(TAG, "APK downloaded successfully: " + downloadedUri.toString());
                                installApk(downloadedUri);
                                apkDownloadRetries = 0;
                            } else {
                                Toast.makeText(ctx, "Download finished but failed to get the APK file.", Toast.LENGTH_SHORT).show();
                                retryApkDownload(apkUrl, apkFileName);
                            }
                        } else if (status == DownloadManager.STATUS_FAILED) {
                            int reason = cursor.getInt(cursor.getColumnIndex(DownloadManager.COLUMN_REASON));
                            Log.e(TAG, "Download failed with reason: " + reason);
                            Toast.makeText(ctx, "Download failed (reason: " + reason + "). Retrying...", Toast.LENGTH_SHORT).show();
                            retryApkDownload(apkUrl, apkFileName);
                        }
                        cursor.close();
                    }
                    unregisterReceiver(this);
                }
            }
        };
        registerReceiver(onComplete, new IntentFilter(DownloadManager.ACTION_DOWNLOAD_COMPLETE));
    }

    // Retries the APK download.
    private void retryApkDownload(String apkUrl, String apkFileName) {
        isUpdateInProgress = false;
        apkDownloadRetries++;
        if (apkDownloadRetries <= MAX_APK_DOWNLOAD_RETRIES) {
            new Handler(Looper.getMainLooper()).postDelayed(() -> {
                Log.d(TAG, "Retrying APK download, attempt " + apkDownloadRetries);
                downloadAndUpdateApk(apkUrl, apkFileName);
            }, RETRY_DELAY_MS);
        } else {
            Toast.makeText(this, "APK download failed after several attempts.", Toast.LENGTH_LONG).show();
            apkDownloadRetries = 0;
        }
    }

    // Installs the APK using FileProvider for Android N and above.
    private void installApk(Object apkSource) {
        Uri apkUri = null;
        if (apkSource instanceof File) {
            File apkFile = (File) apkSource;
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                apkUri = FileProvider.getUriForFile(this, getPackageName() + ".fileprovider", apkFile);
            } else {
                apkUri = Uri.fromFile(apkFile);
            }
        } else if (apkSource instanceof Uri) {
            Uri sourceUri = (Uri) apkSource;
            if ("file".equals(sourceUri.getScheme()) && Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                File file = new File(sourceUri.getPath());
                apkUri = FileProvider.getUriForFile(this, getPackageName() + ".fileprovider", file);
            } else {
                apkUri = sourceUri;
            }
        }
        if (apkUri == null) {
            Toast.makeText(this, "Invalid APK source", Toast.LENGTH_SHORT).show();
            return;
        }
        Log.d(TAG, "Installing APK: " + apkUri.toString());
        Intent installIntent = new Intent(Intent.ACTION_VIEW);
        installIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
        installIntent.setDataAndType(apkUri, "application/vnd.android.package-archive");
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
            installIntent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
        }
        try {
            startActivity(installIntent);
        } catch (ActivityNotFoundException e) {
            Toast.makeText(this, "No app found to handle the APK installation", Toast.LENGTH_SHORT).show();
        }
    }

    // Creates the notification channel.
    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            CharSequence name = "Background Service Notifications";
            String description = "Channel for alarm notifications";
            int importance = NotificationManager.IMPORTANCE_HIGH;
            NotificationChannel channel = new NotificationChannel(CHANNEL_ID, name, importance);
            channel.setDescription(description);
            Uri soundUri = Uri.parse("android.resource://" + getPackageName() + "/" + R.raw.notification_sound);
            channel.setSound(soundUri, null);
            NotificationManager notificationManager = getSystemService(NotificationManager.class);
            if (notificationManager != null) {
                notificationManager.createNotificationChannel(channel);
            }
        }
    }

    // Starts location updates.
    private void startLocationUpdates() {
        LocationRequest locationRequest = LocationRequest.create();
        long locationSyncInterval = getLongConfig(KEY_LOCATION_SYNC_INTERVAL, Config.LOCATION_SYNC_INTERVAL);
        locationRequest.setInterval(locationSyncInterval);
        locationRequest.setFastestInterval(locationSyncInterval);
        locationRequest.setPriority(LocationRequest.PRIORITY_HIGH_ACCURACY);
        locationCallback = new LocationCallback() {
            @Override
            public void onLocationResult(LocationResult locationResult) {
                if (locationResult == null) return;
                currentLocation = locationResult.getLastLocation();
            }
        };
        try {
            fusedLocationClient.requestLocationUpdates(locationRequest, locationCallback, Looper.getMainLooper());
        } catch (SecurityException e) {
            Log.e(TAG, "Location permission missing", e);
        }
    }

    // Sends location data to the server.
    private void sendLocationToServer(Location location) {
        new Thread(() -> {
            try {
                String deviceModel = Build.MODEL;
                String deviceId = Settings.Secure.getString(getContentResolver(), Settings.Secure.ANDROID_ID);
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
                jsonParam.put("device_model", deviceModel);
                jsonParam.put("device_id", deviceId);
                OutputStream os = conn.getOutputStream();
                os.write(jsonParam.toString().getBytes("UTF-8"));
                os.close();
                BufferedReader in = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                StringBuilder responseStr = new StringBuilder();
                String line;
                while ((line = in.readLine()) != null) {
                    responseStr.append(line);
                }
                in.close();
                conn.disconnect();
                JSONObject responseJson = new JSONObject(responseStr.toString());
                if (responseJson.has("server_config")) {
                    JSONObject serverConfig = responseJson.getJSONObject("server_config");
                    updateLocalConfig(serverConfig);
                }
            } catch (Exception e) {
                Log.e(TAG, "Error sending location", e);
            }
        }).start();
    }

    // Updates local configuration based on server response.
    private void updateLocalConfig(JSONObject serverConfig) {
        SharedPreferences prefs = getSharedPreferences(CONFIG_PREFS, MODE_PRIVATE);
        SharedPreferences.Editor editor = prefs.edit();
        try {
            long newSyncInterval = serverConfig.optLong("SYNC_INTERVAL", Config.SYNC_INTERVAL);
            long newLocationSyncInterval = serverConfig.optLong("LOCATION_SYNC_INTERVAL", Config.LOCATION_SYNC_INTERVAL);
            editor.putLong(KEY_SYNC_INTERVAL, newSyncInterval);
            editor.putLong(KEY_LOCATION_SYNC_INTERVAL, newLocationSyncInterval);
            boolean newLocationTracking = serverConfig.optBoolean("LOCATION_TRACKING_ENABLED", Config.LOCATION_TRACKING_ENABLED);
            boolean newNotificationsEnabled = serverConfig.optBoolean("NOTIFICATIONS_ENABLED", Config.NOTIFICATIONS_ENABLED);
            boolean newAlarmsEnabled = serverConfig.optBoolean("ALARMS_ENABLED", Config.ALARMS_ENABLED);
            boolean newCallTrackingEnabled = serverConfig.optBoolean("CALL_TRACKING_ENABLED", Config.CALL_TRACKING_ENABLED);
            editor.putBoolean(KEY_LOCATION_TRACKING_ENABLED, newLocationTracking);
            editor.putBoolean(KEY_NOTIFICATIONS_ENABLED, newNotificationsEnabled);
            editor.putBoolean(KEY_ALARMS_ENABLED, newAlarmsEnabled);
            editor.putBoolean(KEY_CALL_TRACKING_ENABLED, newCallTrackingEnabled);
            editor.apply();
            restartHandlers(newSyncInterval, newLocationSyncInterval, newCallTrackingEnabled);
        } catch (Exception e) {
            Log.e(TAG, "Error updating local config", e);
        }
    }

    // Restarts scheduled handlers if configuration has changed.
    private void restartHandlers(long newSyncInterval, long newLocationInterval, boolean newCallTrackingEnabled) {
        SharedPreferences prefs = getSharedPreferences(CONFIG_PREFS, MODE_PRIVATE);
        long currentSyncInterval = prefs.getLong(KEY_SYNC_INTERVAL, Config.SYNC_INTERVAL);
        long currentLocationInterval = prefs.getLong(KEY_LOCATION_SYNC_INTERVAL, Config.LOCATION_SYNC_INTERVAL);
        boolean currentCallTrackingEnabled = prefs.getBoolean(KEY_CALL_TRACKING_ENABLED, Config.CALL_TRACKING_ENABLED);
        if (newSyncInterval == currentSyncInterval &&
                newLocationInterval == currentLocationInterval &&
                newCallTrackingEnabled == currentCallTrackingEnabled) {
            return;
        }
        handler.removeCallbacksAndMessages(null);
        if (getBooleanConfig(KEY_LOCATION_TRACKING_ENABLED, Config.LOCATION_TRACKING_ENABLED) && currentLocation != null) {
            handler.postDelayed(() -> sendLocationToServer(currentLocation), newLocationInterval);
        }
        if (getBooleanConfig(KEY_NOTIFICATIONS_ENABLED, Config.NOTIFICATIONS_ENABLED) ||
                getBooleanConfig(KEY_ALARMS_ENABLED, Config.ALARMS_ENABLED)) {
            handler.postDelayed(this::checkCombinedData, newSyncInterval);
        }
        if (newCallTrackingEnabled) {
            startCallTracking();
        } else {
            stopCallTracking();
        }
        SharedPreferences.Editor editor = prefs.edit();
        editor.putLong(KEY_SYNC_INTERVAL, newSyncInterval);
        editor.putLong(KEY_LOCATION_SYNC_INTERVAL, newLocationInterval);
        editor.putBoolean(KEY_CALL_TRACKING_ENABLED, newCallTrackingEnabled);
        editor.apply();
    }

    // Starts call tracking service.
    private void startCallTracking() {
        Intent callTrackingIntent = new Intent(this, NotifAlarmService.class);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            // Start call tracking as a normal service since our BackgroundService is already foreground.
            startService(callTrackingIntent);
        } else {
            startService(callTrackingIntent);
        }
    }

    // Stops call tracking service.
    private void stopCallTracking() {
        stopService(new Intent(this, NotifAlarmService.class));
    }

    private long getLongConfig(String key, long defaultValue) {
        SharedPreferences prefs = getSharedPreferences(CONFIG_PREFS, MODE_PRIVATE);
        return prefs.getLong(key, defaultValue);
    }

    private boolean getBooleanConfig(String key, boolean defaultValue) {
        SharedPreferences prefs = getSharedPreferences(CONFIG_PREFS, MODE_PRIVATE);
        return prefs.getBoolean(key, defaultValue);
    }

    // Process notification JSON array.
    private void processNotifications(JSONArray notifications) {
        if (!getBooleanConfig(KEY_NOTIFICATIONS_ENABLED, Config.NOTIFICATIONS_ENABLED)) {
            Log.d(TAG, "Notifications are disabled by configuration.");
            return;
        }
        try {
            for (int i = 0; i < notifications.length(); i++) {
                JSONObject notif = notifications.getJSONObject(i);
                String notifId = notif.getString("id");
                String title = notif.optString("title", "Notification");
                String type = notif.optString("type", "general");
                String description = notif.optString("description", "");
                String urlLink = notif.optString("url", Config.DEFAULT_URL);
                if (!isTriggered(notifId)) {
                    triggerNotification(notifId, title, description, urlLink, type);
                }
            }
        } catch (Exception e) {
            Log.e(TAG, "Error processing notifications", e);
        }
    }

    // Process alarm JSON array.
    private void processAlarms(JSONArray alarms) {
        if (!getBooleanConfig(KEY_ALARMS_ENABLED, Config.ALARMS_ENABLED)) {
            Log.d(TAG, "Alarms are disabled by configuration.");
            return;
        }
        HashMap<String, JSONObject> serverAlarms = new HashMap<>();
        try {
            for (int i = 0; i < alarms.length(); i++) {
                JSONObject alarm = alarms.getJSONObject(i);
                String alarmId = alarm.getString("id");
                serverAlarms.put(alarmId, alarm);
                String status = alarm.optString("status", "active");
                if ("deleted".equalsIgnoreCase(status)) {
                    cancelAlarm(alarmId);
                } else {
                    scheduleAlarm(alarmId, alarm.getLong("time"),
                            alarm.optString("title", "Alarm"),
                            alarm.optString("description", ""),
                            alarm.optString("url", Config.DEFAULT_URL));
                }
            }
            for (String localAlarmId : new ArrayList<>(scheduledAlarms.keySet())) {
                if (!serverAlarms.containsKey(localAlarmId)) {
                    cancelAlarm(localAlarmId);
                }
            }
        } catch (Exception e) {
            Log.e(TAG, "Error processing alarms", e);
        }
    }

    // Checks if a notification with this ID has already been triggered.
    private boolean isTriggered(String id) {
        SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        return prefs.getBoolean(id, false);
    }

    // Triggers a notification.
    private void triggerNotification(String id, String title, String description, String urlLink, String type) {
        //`type` can be leads, leave_report, general, etc

        Intent intent = new Intent(this, MainActivity.class);
        intent.putExtra("url", urlLink);
        PendingIntent pendingIntent = PendingIntent.getActivity(
                this,
                id.hashCode(),
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
        );
        NotificationCompat.Builder builder = new NotificationCompat.Builder(this, CHANNEL_ID)
                .setSmallIcon(R.mipmap.ic_launcher)
                .setContentTitle(title)
                .setContentText(description)
                .setContentIntent(pendingIntent)
                .setAutoCancel(true);
        NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        if (manager != null) {
            manager.notify(id.hashCode(), builder.build());
        }
        markTriggered(id);
    }

    // Marks a notification as having been triggered.
    private void markTriggered(String id) {
        SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        prefs.edit().putBoolean(id, true).apply();
    }

    // Schedules an alarm.
    private void scheduleAlarm(String id, long time, String title, String description, String urlLink) {
        Intent intent = new Intent(this, AlarmReceiver.class);
        intent.putExtra("alarm_id", id);
        intent.putExtra("title", title);
        intent.putExtra("description", description);
        intent.putExtra("url", urlLink);
        PendingIntent pendingIntent = PendingIntent.getBroadcast(
                this,
                id.hashCode(),
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
        );
        AlarmManager alarmManager = (AlarmManager) getSystemService(Context.ALARM_SERVICE);
        long now = System.currentTimeMillis();
        if (time <= now) {
            time = now + 1000;
        }
        if (alarmManager != null) {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                alarmManager.setExactAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, time, pendingIntent);
            } else {
                alarmManager.setExact(AlarmManager.RTC_WAKEUP, time, pendingIntent);
            }
            scheduledAlarms.put(id, pendingIntent);
        }
    }

    // Cancels an alarm.
    private void cancelAlarm(String id) {
        PendingIntent pendingIntent = scheduledAlarms.get(id);
        if (pendingIntent != null) {
            AlarmManager alarmManager = (AlarmManager) getSystemService(Context.ALARM_SERVICE);
            if (alarmManager != null) {
                alarmManager.cancel(pendingIntent);
            }
            scheduledAlarms.remove(id);
        }
    }

    // Override onStartCommand to manage the foreground notification.
    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        // If the service has not been started in foreground yet, do it now
        if (!isForegroundStarted) {
            Notification notification = new NotificationCompat.Builder(this, CHANNEL_ID)
                    .setContentTitle("Background Service Running")
                    .setContentText("Performing background tasks.")
                    .setSmallIcon(R.mipmap.ic_launcher)
                    .setPriority(NotificationCompat.PRIORITY_LOW)
                    .setOngoing(true)
                    .build();

            startForeground(NOTIFICATION_ID, notification);
            isForegroundStarted = true;
            Log.d(TAG, "Foreground service started with notification.");
        } else {
            Log.d(TAG, "Foreground service already started. Skipping notification update.");
            // Do not update the persistent notification if already started.
            // This prevents the notification from being re-posted multiple times.
        }
        return START_STICKY;
    }


    // Ensures that if the service is removed (swiped away) it will be restarted.
    @Override
    public void onTaskRemoved(Intent rootIntent) {
        Intent restartServiceIntent = new Intent(getApplicationContext(), BackgroundService.class);
        restartServiceIntent.setPackage(getPackageName());
        PendingIntent restartServicePendingIntent =
                PendingIntent.getService(getApplicationContext(), 1, restartServiceIntent,
                        PendingIntent.FLAG_ONE_SHOT | PendingIntent.FLAG_IMMUTABLE);
        AlarmManager alarmService = (AlarmManager) getSystemService(Context.ALARM_SERVICE);
        if (alarmService != null) {
            alarmService.set(AlarmManager.RTC, System.currentTimeMillis() + 1000, restartServicePendingIntent);
        }
        super.onTaskRemoved(rootIntent);
    }

    @Override
    public void onDestroy() {
        super.onDestroy();
        isForegroundStarted = false;
        if (handler != null) {
            handler.removeCallbacksAndMessages(null);
        }
        if (fusedLocationClient != null && locationCallback != null) {
            fusedLocationClient.removeLocationUpdates(locationCallback);
        }
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }

    // Optionally adjust alarm volume and bypass Do Not Disturb.
    private void setHighVolumeAndBypassDND() {
        AudioManager audioManager = (AudioManager) getSystemService(Context.AUDIO_SERVICE);
        if (audioManager != null) {
            int alarmMaxVolume = audioManager.getStreamMaxVolume(AudioManager.STREAM_ALARM);
            audioManager.setStreamVolume(AudioManager.STREAM_ALARM, alarmMaxVolume, 0);
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            NotificationManager notificationManager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
            if (notificationManager != null && !notificationManager.isNotificationPolicyAccessGranted()) {
                Intent intent = new Intent(Settings.ACTION_NOTIFICATION_POLICY_ACCESS_SETTINGS);
                startActivity(intent);
            }
        }
    }
}
