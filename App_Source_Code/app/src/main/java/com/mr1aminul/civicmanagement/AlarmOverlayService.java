package com.mr1aminul.civicmanagement;

import android.app.KeyguardManager;
import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.PixelFormat;
import android.media.AudioAttributes;
import android.media.AudioManager;
import android.media.MediaPlayer;
import android.net.Uri;
import android.os.Build;
import android.os.Handler;
import android.os.IBinder;
import android.os.PowerManager;
import android.os.VibrationEffect;
import android.os.Vibrator;
import android.provider.Settings;
import android.util.Log;
import android.view.LayoutInflater;
import android.view.View;
import android.view.WindowManager;
import android.widget.Button;
import android.widget.TextView;

import androidx.annotation.Nullable;
import androidx.core.app.NotificationCompat;

public class AlarmOverlayService extends Service {
    private static final String TAG = "AlarmOverlayService";
    private static final int NOTIFICATION_ID = 2001;
    private static final String CHANNEL_ID = "AlarmOverlayChannel";
    private static final String PREFS_NAME = "AlarmPrefs";
    private static final String KEY_ACTIVE_ALARM_ID = "active_alarm_id";
    
    private WindowManager windowManager;
    private View alarmView;
    private MediaPlayer mediaPlayer;
    private Vibrator vibrator;
    private PowerManager.WakeLock wakeLock;
    private Handler handler = new Handler();
    
    private String currentAlarmId;

    @Override
    public void onCreate() {
        super.onCreate();
        windowManager = (WindowManager) getSystemService(WINDOW_SERVICE);
        vibrator = (Vibrator) getSystemService(VIBRATOR_SERVICE);
        
        createNotificationChannel();
        wakeUpScreen();
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        if (intent == null) {
            stopSelf();
            return START_NOT_STICKY;
        }

        currentAlarmId = intent.getStringExtra("alarm_id");
        String title = intent.getStringExtra("title");
        String description = intent.getStringExtra("description");
        String urlLink = intent.getStringExtra("url");

        Log.d(TAG, "Starting overlay for alarm: " + currentAlarmId);

        // Start as foreground service
        startForeground(NOTIFICATION_ID, createNotification(title));

        // Check overlay permission
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M && !Settings.canDrawOverlays(this)) {
            Log.e(TAG, "Overlay permission not granted");
            // Fallback to activity
            startAlarmActivity(currentAlarmId, title, description, urlLink);
            stopSelf();
            return START_NOT_STICKY;
        }

        showAlarmOverlay(title, description, urlLink);
        startAlarmSound();
        startVibration();

        // Auto dismiss after 10 minutes
        handler.postDelayed(() -> {
            Log.d(TAG, "Auto dismissing alarm: " + currentAlarmId);
            stopAlarm();
        }, 10 * 60 * 1000);

        return START_NOT_STICKY;
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                CHANNEL_ID,
                "Alarm Overlay",
                NotificationManager.IMPORTANCE_HIGH
            );
            channel.setDescription("Alarm overlay notifications");
            
            NotificationManager manager = getSystemService(NotificationManager.class);
            if (manager != null) {
                manager.createNotificationChannel(channel);
            }
        }
    }

    private Notification createNotification(String title) {
        return new NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("Alarm Active")
            .setContentText(title)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setOngoing(true)
            .build();
    }

    private void showAlarmOverlay(String title, String description, String urlLink) {
        try {
            LayoutInflater inflater = LayoutInflater.from(this);
            alarmView = inflater.inflate(R.layout.alarm_overlay, null);

            int layoutFlag;
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                layoutFlag = WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY;
            } else {
                layoutFlag = WindowManager.LayoutParams.TYPE_PHONE;
            }

            WindowManager.LayoutParams params = new WindowManager.LayoutParams(
                WindowManager.LayoutParams.MATCH_PARENT,
                WindowManager.LayoutParams.MATCH_PARENT,
                layoutFlag,
                WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON |
                WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED |
                WindowManager.LayoutParams.FLAG_TURN_SCREEN_ON |
                WindowManager.LayoutParams.FLAG_DISMISS_KEYGUARD,
                PixelFormat.TRANSLUCENT
            );

            TextView tvTitle = alarmView.findViewById(R.id.overlayTitle);
            TextView tvDescription = alarmView.findViewById(R.id.overlayDescription);
            Button btnViewDetails = alarmView.findViewById(R.id.btnOverlayViewDetails);
            Button btnDismiss = alarmView.findViewById(R.id.btnOverlayDismiss);

            tvTitle.setText(title);
            tvDescription.setText(description);

            btnViewDetails.setOnClickListener(v -> {
                Log.d(TAG, "View details clicked for: " + currentAlarmId);
                stopAlarm();
                
                Intent mainIntent = new Intent(this, MainActivity.class);
                mainIntent.putExtra("url", urlLink);
                mainIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
                startActivity(mainIntent);
            });

            btnDismiss.setOnClickListener(v -> {
                Log.d(TAG, "Dismiss clicked for: " + currentAlarmId);
                stopAlarm();
            });

            windowManager.addView(alarmView, params);
            Log.d(TAG, "Overlay displayed for: " + currentAlarmId);

        } catch (Exception e) {
            Log.e(TAG, "Error showing overlay", e);
            // Fallback to activity
            startAlarmActivity(currentAlarmId, title, description, urlLink);
            stopSelf();
        }
    }

    private void startAlarmActivity(String alarmId, String title, String description, String url) {
        Intent intent = new Intent(this, AlarmActivity.class);
        intent.putExtra("alarm_id", alarmId);
        intent.putExtra("title", title);
        intent.putExtra("description", description);
        intent.putExtra("url", url);
        intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
        startActivity(intent);
    }

    private void startAlarmSound() {
        try {
            AudioAttributes audioAttributes = new AudioAttributes.Builder()
                .setUsage(AudioAttributes.USAGE_ALARM)
                .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                .build();

            Uri alarmUri = Uri.parse("android.resource://" + getPackageName() + "/" + R.raw.alarm_sound);
            mediaPlayer = new MediaPlayer();
            mediaPlayer.setDataSource(this, alarmUri);
            mediaPlayer.setAudioAttributes(audioAttributes);
            mediaPlayer.setLooping(true);
            mediaPlayer.prepare();
            mediaPlayer.start();
            
            Log.d(TAG, "Alarm sound started");
        } catch (Exception e) {
            Log.e(TAG, "Error starting alarm sound", e);
        }
    }

    private void startVibration() {
        if (vibrator != null) {
            try {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    vibrator.vibrate(VibrationEffect.createWaveform(new long[]{0, 500, 1000}, 0));
                } else {
                    vibrator.vibrate(new long[]{0, 500, 1000}, 0);
                }
                Log.d(TAG, "Vibration started");
            } catch (Exception e) {
                Log.e(TAG, "Error starting vibration", e);
            }
        }
    }

    private void wakeUpScreen() {
        PowerManager powerManager = (PowerManager) getSystemService(Context.POWER_SERVICE);
        if (powerManager != null) {
            wakeLock = powerManager.newWakeLock(
                PowerManager.FULL_WAKE_LOCK | PowerManager.ACQUIRE_CAUSES_WAKEUP | PowerManager.ON_AFTER_RELEASE,
                "AlarmOverlayService:WakeLock"
            );
            wakeLock.acquire(10 * 60 * 1000L);
        }
    
        // Don't try to dismiss keyguard from service - let the activity handle it
    }

    private void stopAlarm() {
        Log.d(TAG, "Stopping alarm: " + currentAlarmId);
        
        // Clear active alarm ID
        SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        prefs.edit().remove(KEY_ACTIVE_ALARM_ID).apply();
        
        // Stop media player
        if (mediaPlayer != null) {
            try {
                if (mediaPlayer.isPlaying()) {
                    mediaPlayer.stop();
                }
                mediaPlayer.release();
                mediaPlayer = null;
            } catch (Exception e) {
                Log.e(TAG, "Error stopping media player", e);
            }
        }

        // Stop vibration
        if (vibrator != null) {
            vibrator.cancel();
        }

        // Remove overlay
        if (alarmView != null && windowManager != null) {
            try {
                windowManager.removeView(alarmView);
                alarmView = null;
            } catch (Exception e) {
                Log.e(TAG, "Error removing overlay", e);
            }
        }

        // Release wake lock
        if (wakeLock != null && wakeLock.isHeld()) {
            wakeLock.release();
            wakeLock = null;
        }

        // Remove handlers
        handler.removeCallbacksAndMessages(null);

        stopSelf();
    }

    @Override
    public void onDestroy() {
        Log.d(TAG, "Service destroyed");
        stopAlarm();
        super.onDestroy();
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }
}
