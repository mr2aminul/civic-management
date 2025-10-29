package com.mr1aminul.civicmanagement;

import android.content.Context;
import android.content.Intent;
import android.util.Log;

import com.mr1aminul.civicmanagement.database.DatabaseManager;

import java.util.HashMap;
import java.util.Map;

/**
 * Manager for handling lead notifications and bubble display
 * Coordinates between server notifications and UI display
 */
public class LeadNotificationManager {
    private static final String TAG = "LeadNotificationManager";
    private static LeadNotificationManager instance;
    private Context context;
    private DatabaseManager dbManager;
    private Map<String, Long> activeBubbles = new HashMap<>();
    
    private LeadNotificationManager(Context context) {
        this.context = context.getApplicationContext();
        this.dbManager = DatabaseManager.getInstance(context);
    }
    
    public static synchronized LeadNotificationManager getInstance(Context context) {
        if (instance == null) {
            instance = new LeadNotificationManager(context);
        }
        return instance;
    }
    
    /**
     * Show a lead notification as a floating bubble
     * @param leadId Lead ID from server
     * @param title Lead name or notification title
     * @param message Notification message
     * @param url URL to open when bubble is clicked
     */
    public void showLeadBubble(String leadId, String title, String message, String url) {
        try {
            // Check if bubble already exists for this lead
            if (activeBubbles.containsKey(leadId)) {
                Log.d(TAG, "Bubble already active for lead: " + leadId);
                return;
            }
            
            // Store lead in database
            dbManager.insertNotification(
                    "lead_" + leadId,
                    "", // userId will be set by caller
                    title,
                    message,
                    "leads",
                    url
            );
            
            // Show bubble
            Intent intent = new Intent(context, LeadBubbleService.class);
            intent.putExtra("action", "SHOW_LEAD_NOTIFICATION");
            intent.putExtra("lead_id", leadId);
            intent.putExtra("title", title);
            intent.putExtra("message", message);
            intent.putExtra("url", url);
            context.startService(intent);
            
            activeBubbles.put(leadId, System.currentTimeMillis());
            Log.d(TAG, "Lead bubble shown for: " + leadId);
            
        } catch (Exception e) {
            Log.e(TAG, "Error showing lead bubble", e);
        }
    }
    
    /**
     * Hide a lead notification bubble
     * @param leadId Lead ID to hide
     */
    public void hideLeadBubble(String leadId) {
        try {
            Intent intent = new Intent(context, LeadBubbleService.class);
            intent.putExtra("action", "HIDE_LEAD_NOTIFICATION");
            intent.putExtra("lead_id", leadId);
            context.startService(intent);
            
            activeBubbles.remove(leadId);
            Log.d(TAG, "Lead bubble hidden for: " + leadId);
            
        } catch (Exception e) {
            Log.e(TAG, "Error hiding lead bubble", e);
        }
    }
    
    /**
     * Hide all active lead bubbles
     */
    public void hideAllBubbles() {
        for (String leadId : activeBubbles.keySet()) {
            hideLeadBubble(leadId);
        }
        activeBubbles.clear();
    }
    
    /**
     * Get count of active bubbles
     */
    public int getActiveBubbleCount() {
        return activeBubbles.size();
    }
    
    /**
     * Check if a specific lead has an active bubble
     */
    public boolean hasBubble(String leadId) {
        return activeBubbles.containsKey(leadId);
    }
}
