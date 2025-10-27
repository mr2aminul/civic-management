//NotifAlarmService.java actually call history tracking service
package com.mr1aminul.civicmanagement;

import android.Manifest;
import android.app.AlarmManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageInfo;
import android.content.pm.PackageManager;
import android.database.Cursor;
import android.net.ConnectivityManager;
import android.net.NetworkInfo;
import android.os.Build;
import android.os.Handler;
import android.os.IBinder;
import android.os.Looper;
import android.provider.CallLog;
import android.telephony.PhoneStateListener;
import android.telephony.TelephonyManager;
import android.util.Log;

import androidx.annotation.Nullable;
import androidx.core.content.ContextCompat;

import org.json.JSONArray;
import org.json.JSONObject;

import java.net.HttpURLConnection;
import java.net.InetAddress;
import java.net.URL;
import java.net.URLEncoder;
import java.net.UnknownHostException;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;
import java.util.TimeZone;

public class NotifAlarmService extends Service {
    private static final String TAG = "NotifAlarmService";
    private static final String PREFS_NAME = "app_prefs";
    private static final String PREF_USER_ID = "user_id2";
    // SharedPreferences keys for pending call data.
    private static final String PENDING_PREFS = "pending_calls_prefs";
    private static final String KEY_PENDING_CALLS = "pending_calls";

    private TelephonyManager telephonyManager;
    private CallStateListener callStateListener;
    private Handler pendingHandler;
    private Runnable pendingRunnable;

    @Override
    public void onCreate() {
        super.onCreate();

        telephonyManager = (TelephonyManager) getSystemService(Context.TELEPHONY_SERVICE);
        callStateListener = new CallStateListener();

        if (ContextCompat.checkSelfPermission(this, Manifest.permission.READ_CALL_LOG) == PackageManager.PERMISSION_GRANTED &&
                ContextCompat.checkSelfPermission(this, Manifest.permission.READ_PHONE_STATE) == PackageManager.PERMISSION_GRANTED) {
            telephonyManager.listen(callStateListener, PhoneStateListener.LISTEN_CALL_STATE);
            Log.d(TAG, "Call tracking service started!");
        } else {
            Log.e(TAG, "Required permissions not granted");
        }

        pendingHandler = new Handler(Looper.getMainLooper());
        pendingRunnable = new Runnable() {
            @Override
            public void run() {
                processPendingCallData();
                pendingHandler.postDelayed(this, 30000);
            }
        };
        pendingHandler.post(pendingRunnable);
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        Log.d(TAG, "NotifAlarmService started");
        // Not calling startForeground() so that the BackgroundService's notification remains the only one.
        return START_STICKY;
    }

    // Ensures the service restarts if removed.
    @Override
    public void onTaskRemoved(Intent rootIntent) {
        Intent restartServiceIntent = new Intent(getApplicationContext(), NotifAlarmService.class);
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
        if (telephonyManager != null && callStateListener != null) {
            telephonyManager.listen(callStateListener, PhoneStateListener.LISTEN_NONE);
        }
        if (pendingHandler != null) {
            pendingHandler.removeCallbacks(pendingRunnable);
        }
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }

    // Inner class to listen for call state changes.
    private class CallStateListener extends PhoneStateListener {
        private boolean callActive = false;
        private long callStartTime = 0;
        private String outgoingNumber = null;

        @Override
        public void onCallStateChanged(int state, String incomingNumber) {
            super.onCallStateChanged(state, incomingNumber);
            Log.d(TAG, "onCallStateChanged: state=" + state + ", incomingNumber=" + incomingNumber);
            switch (state) {
                case TelephonyManager.CALL_STATE_RINGING:
                    Log.d(TAG, "Phone is ringing");
                    break;
                case TelephonyManager.CALL_STATE_OFFHOOK:
                    callActive = true;
                    outgoingNumber = incomingNumber;
                    callStartTime = System.currentTimeMillis();
                    Log.d(TAG, "Call is active (OFFHOOK)");
                    break;
                case TelephonyManager.CALL_STATE_IDLE:
                    if (callActive) {
                        long callEndTime = System.currentTimeMillis();
                        callActive = false;
                        Log.d(TAG, "Call ended, querying latest call log...");
                        new Handler(Looper.getMainLooper()).postDelayed(() ->
                                queryLatestCall(outgoingNumber, (callEndTime - callStartTime) / 1000, callEndTime), 2000);
                    }
                    break;
                default:
                    Log.d(TAG, "Unknown call state: " + state);
                    break;
            }
        }

        private void queryLatestCall(String outgoingNumber, long computedDuration, long callEndTime) {
            new Thread(() -> {
                try {
                    Cursor cursor = getContentResolver().query(
                            CallLog.Calls.CONTENT_URI,
                            null,
                            null,
                            null,
                            CallLog.Calls.DATE + " DESC"
                    );
                    if (cursor != null && cursor.moveToFirst()) {
                        String number = cursor.getString(cursor.getColumnIndex(CallLog.Calls.NUMBER));
                        String durationStr = cursor.getString(cursor.getColumnIndex(CallLog.Calls.DURATION));
                        int callLogType = cursor.getInt(cursor.getColumnIndex(CallLog.Calls.TYPE));

                        long logDuration = 0;
                        try {
                            logDuration = Long.parseLong(durationStr);
                        } catch (NumberFormatException e) {
                            Log.e(TAG, "Error parsing duration: " + e.getMessage());
                        }

                        String status;
                        if (outgoingNumber != null && outgoingNumber.equals(number)) {
                            status = (logDuration == 0 || logDuration < 5) ? "unreachable" : "answered";
                        } else {
                            status = (callLogType == CallLog.Calls.MISSED_TYPE || logDuration == 0) ? "missed_call" : "answered";
                        }

                        String callType = (outgoingNumber != null && outgoingNumber.equals(number)) ? "outgoing" : "incoming";
                        String dhakaTimestamp = getDhakaTimestamp(callEndTime);

                        SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
                        String userId = prefs.getString(PREF_USER_ID, null);

                        Log.d(TAG, "Call details: Number=" + number + ", Status=" + status + ", Duration=" + durationStr +
                                ", Type=" + callType + ", UserId=" + userId + ", Timestamp=" + dhakaTimestamp);

                        if (userId != null) {
                            JSONObject callDataJson = new JSONObject();
                            callDataJson.put("user_id", userId);
                            callDataJson.put("number", number);
                            callDataJson.put("status", status);
                            callDataJson.put("duration", logDuration);
                            callDataJson.put("call_type", callType);
                            callDataJson.put("timestamp", dhakaTimestamp);
                            sendCallData(callDataJson);
                        } else {
                            Log.e(TAG, "User ID not available; skipping server call.");
                        }
                    }
                    if (cursor != null) {
                        cursor.close();
                    }
                } catch (Exception e) {
                    Log.e(TAG, "Error querying call log", e);
                }
            }).start();
        }
    }

    // Returns a formatted timestamp in Dhaka timezone.
    private String getDhakaTimestamp(long epochMillis) {
        try {
            SimpleDateFormat sdf = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault());
            sdf.setTimeZone(TimeZone.getTimeZone("Asia/Dhaka"));
            return sdf.format(new Date(epochMillis));
        } catch (Exception e) {
            Log.e(TAG, "Error formatting timestamp: " + e.getMessage());
            return String.valueOf(epochMillis);
        }
    }

    // Checks if network connectivity exists.
    private boolean isNetworkAvailable() {
        try {
            ConnectivityManager connectivityManager = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
            if (connectivityManager != null) {
                NetworkInfo networkInfo = connectivityManager.getActiveNetworkInfo();
                if (networkInfo != null && networkInfo.isConnected()) {
                    return isInternetWorking();
                }
            }
        } catch (Exception e) {
            Log.e(TAG, "Network check error: " + e.getMessage());
        }
        return false;
    }

    // Checks if internet access is working.
    private boolean isInternetWorking() {
        try {
            InetAddress address = InetAddress.getByName("8.8.8.8");
            return !address.equals("");
        } catch (UnknownHostException e) {
            Log.e(TAG, "No internet access: " + e.getMessage());
            return false;
        }
    }

    // Sends call data to the server or stores it locally if no network exists.
    private void sendCallData(JSONObject callDataJson) {
        if (isNetworkAvailable()) {
            new Thread(() -> {
                try {
                    sendDataToServer(callDataJson);
                } catch (Exception e) {
                    Log.e(TAG, "Error sending call data, storing pending: " + e.getMessage());
                    storePendingCallData(callDataJson);
                }
            }).start();
        } else {
            Log.d(TAG, "No network; storing call data for later");
            storePendingCallData(callDataJson);
        }
    }

    // Sends the call data to the server using a GET request.
    private void sendDataToServer(JSONObject callDataJson) throws Exception {
        String appVersion = getAppVersion();
        String baseUrl = "https://civicgroupbd.com/app_api/calls.php?app_version=" + appVersion;
        String query = "&user_id=" + URLEncoder.encode(callDataJson.getString("user_id"), "UTF-8") +
                "&number=" + URLEncoder.encode(callDataJson.getString("number"), "UTF-8") +
                "&status=" + URLEncoder.encode(callDataJson.getString("status"), "UTF-8") +
                "&duration=" + callDataJson.getLong("duration") +
                "&call_type=" + URLEncoder.encode(callDataJson.getString("call_type"), "UTF-8") +
                "&timestamp=" + URLEncoder.encode(callDataJson.getString("timestamp"), "UTF-8");
        String serverUrl = baseUrl + query;
        URL url = new URL(serverUrl);
        HttpURLConnection connection = (HttpURLConnection) url.openConnection();
        connection.setRequestMethod("GET");
        connection.setConnectTimeout(5000);
        connection.setReadTimeout(5000);
        int responseCode = connection.getResponseCode();
        Log.d(TAG, "Server response code: " + responseCode);
        connection.disconnect();
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

    // Stores pending call data in SharedPreferences.
    private void storePendingCallData(JSONObject callDataJson) {
        SharedPreferences prefs = getSharedPreferences(PENDING_PREFS, MODE_PRIVATE);
        String pendingData = prefs.getString(KEY_PENDING_CALLS, "[]");
        try {
            JSONArray jsonArray = new JSONArray(pendingData);
            jsonArray.put(callDataJson);
            prefs.edit().putString(KEY_PENDING_CALLS, jsonArray.toString()).apply();
            Log.d(TAG, "Stored pending call data");
        } catch (Exception e) {
            Log.e(TAG, "Error storing pending call data: " + e.getMessage());
        }
    }

    // Processes pending call data from SharedPreferences.
    private void processPendingCallData() {
        if (!isNetworkAvailable()) {
            return;
        }
        new Thread(() -> {
            SharedPreferences prefs = getSharedPreferences(PENDING_PREFS, MODE_PRIVATE);
            String pendingData = prefs.getString(KEY_PENDING_CALLS, "[]");
            try {
                JSONArray jsonArray = new JSONArray(pendingData);
                JSONArray remaining = new JSONArray();
                for (int i = 0; i < jsonArray.length(); i++) {
                    JSONObject callDataJson = jsonArray.getJSONObject(i);
                    try {
                        sendDataToServer(callDataJson);
                    } catch (Exception e) {
                        Log.e(TAG, "Error sending pending call data", e);
                        remaining.put(callDataJson);
                    }
                }
                prefs.edit().putString(KEY_PENDING_CALLS, remaining.toString()).apply();
            } catch (Exception e) {
                Log.e(TAG, "Error processing pending call data: " + e.getMessage());
            }
        }).start();
    }
}
