package com.mr1aminul.civicmanagement;

import android.app.Activity;
import android.content.Intent;
import android.content.SharedPreferences;
import android.media.Ringtone;
import android.media.RingtoneManager;
import android.net.Uri;
import android.os.Bundle;
import android.os.Vibrator;
import android.util.Log;
import android.view.View;
import android.view.WindowManager;
import android.widget.Button;
import android.widget.TextView;

public class AlarmActivity extends Activity {
    private static final String TAG = "AlarmActivity";
    private static final String PREFS_NAME = "AlarmPrefs";
    private static final String KEY_ACTIVE_ALARM_ID = "active_alarm_id";

    private Ringtone ringtone;
    private Vibrator vibrator;
    private String alarmId;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        
        // Show on lock screen
        if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O_MR1) {
            setShowWhenLocked(true);
            setTurnScreenOn(true);
        } else {
            getWindow().addFlags(
                WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED |
                WindowManager.LayoutParams.FLAG_TURN_SCREEN_ON |
                WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON |
                WindowManager.LayoutParams.FLAG_DISMISS_KEYGUARD
            );
        }
        
        setContentView(R.layout.activity_alarm);

        vibrator = (Vibrator) getSystemService(VIBRATOR_SERVICE);

        Intent intent = getIntent();
        alarmId = intent.getStringExtra("alarm_id");
        String title = intent.getStringExtra("title");
        String description = intent.getStringExtra("description");
        String urlLink = intent.getStringExtra("url");

        Log.d(TAG, "Alarm activity started for: " + alarmId);

        TextView tvTitle = findViewById(R.id.alarmTitle);
        TextView tvDescription = findViewById(R.id.alarmDescription);
        Button btnViewDetails = findViewById(R.id.btnViewDetails);
        Button btnDismiss = findViewById(R.id.btnDismiss);

        tvTitle.setText(title);
        tvDescription.setText(description);

        startAlarmSound();
        startVibration();

        btnViewDetails.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                Log.d(TAG, "View details clicked");
                stopAlarm();
                
                Intent mainIntent = new Intent(AlarmActivity.this, MainActivity.class);
                mainIntent.putExtra("url", urlLink);
                mainIntent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP);
                startActivity(mainIntent);
                finish();
            }
        });

        btnDismiss.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                Log.d(TAG, "Dismiss clicked");
                stopAlarm();
                finish();
            }
        });
    }

    private void startAlarmSound() {
        try {
            Uri alarmUri = Uri.parse("android.resource://" + getPackageName() + "/" + R.raw.alarm_sound);
            ringtone = RingtoneManager.getRingtone(this, alarmUri);
            if (ringtone != null) {
                ringtone.play();
            }
        } catch (Exception e) {
            Log.e(TAG, "Error starting alarm sound", e);
        }
    }

    private void startVibration() {
        if (vibrator != null) {
            try {
                long[] pattern = {0, 500, 1000};
                if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
                    vibrator.vibrate(android.os.VibrationEffect.createWaveform(pattern, 0));
                } else {
                    vibrator.vibrate(pattern, 0);
                }
            } catch (Exception e) {
                Log.e(TAG, "Error starting vibration", e);
            }
        }
    }

    private void stopAlarm() {
        // Clear active alarm ID
        SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        prefs.edit().remove(KEY_ACTIVE_ALARM_ID).apply();
        
        if (ringtone != null && ringtone.isPlaying()) {
            ringtone.stop();
        }
        
        if (vibrator != null) {
            vibrator.cancel();
        }
    }

    @Override
    protected void onDestroy() {
        stopAlarm();
        super.onDestroy();
    }

    @Override
    public void onBackPressed() {
        // Prevent back button from dismissing alarm
        // User must use dismiss or view details buttons
    }
}
