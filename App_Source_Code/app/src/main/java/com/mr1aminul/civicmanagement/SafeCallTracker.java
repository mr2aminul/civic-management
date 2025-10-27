package com.mr1aminul.civicmanagement;

import android.content.Context;
import android.content.SharedPreferences;
import android.telephony.PhoneStateListener;
import android.telephony.TelephonyManager;
import android.util.Log;

/**
 * Safer implementation of call tracking that's less likely to trigger Play Protect
 */
public class SafeCallTracker {
    private static final String TAG = "SafeCallTracker";
    private Context context;
    private TelephonyManager telephonyManager;
    private CallStateListener callStateListener;
    
    public SafeCallTracker(Context context) {
        this.context = context;
        this.telephonyManager = (TelephonyManager) context.getSystemService(Context.TELEPHONY_SERVICE);
    }
    
    public void startTracking() {
        // Only start if user has explicitly consented
        SharedPreferences prefs = context.getSharedPreferences("app_prefs", Context.MODE_PRIVATE);
        boolean consentGiven = prefs.getBoolean("consent_given", false);
        
        if (!consentGiven) {
            Log.d(TAG, "Call tracking not started - no user consent");
            return;
        }
        
        callStateListener = new CallStateListener();
        try {
            telephonyManager.listen(callStateListener, PhoneStateListener.LISTEN_CALL_STATE);
            Log.d(TAG, "Call tracking started with user consent");
        } catch (SecurityException e) {
            Log.e(TAG, "Permission denied for call tracking", e);
        }
    }
    
    public void stopTracking() {
        if (telephonyManager != null && callStateListener != null) {
            telephonyManager.listen(callStateListener, PhoneStateListener.LISTEN_NONE);
        }
    }
    
    private class CallStateListener extends PhoneStateListener {
        @Override
        public void onCallStateChanged(int state, String phoneNumber) {
            // Minimal logging to reduce privacy concerns
            switch (state) {
                case TelephonyManager.CALL_STATE_IDLE:
                    Log.d(TAG, "Call ended - business monitoring");
                    break;
                case TelephonyManager.CALL_STATE_RINGING:
                    Log.d(TAG, "Incoming call - business monitoring");
                    break;
                case TelephonyManager.CALL_STATE_OFFHOOK:
                    Log.d(TAG, "Call active - business monitoring");
                    break;
            }
        }
    }
}
