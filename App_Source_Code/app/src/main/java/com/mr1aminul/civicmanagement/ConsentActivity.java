package com.mr1aminul.civicmanagement;

import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Bundle;
import android.view.View;
import android.widget.Button;
import android.widget.CheckBox;
import android.widget.ScrollView;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

public class ConsentActivity extends AppCompatActivity {
    private static final String TAG = "ConsentActivity";
    private static final String PREFS_NAME = "consent_prefs";
    private static final String KEY_CONSENT_GIVEN = "consent_given";
    private static final String KEY_CONSENT_TIMESTAMP = "consent_timestamp";

    private ScrollView termsScrollView;
    private TextView termsTextView;
    private CheckBox consentCheckbox;
    private Button acceptButton;
    private Button declineButton;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        boolean consentGiven = prefs.getBoolean(KEY_CONSENT_GIVEN, false);

        if (consentGiven) {
            proceedToPermissions();
            return;
        }

        setContentView(R.layout.activity_consent);

        initializeViews();
        setupListeners();
        loadTermsAndConditions();
    }

    private void initializeViews() {
        termsScrollView = findViewById(R.id.termsScrollView);
        termsTextView = findViewById(R.id.termsTextView);
        consentCheckbox = findViewById(R.id.consentCheckbox);
        acceptButton = findViewById(R.id.acceptButton);
        declineButton = findViewById(R.id.declineButton);

        acceptButton.setEnabled(false);
    }

    private void setupListeners() {
        consentCheckbox.setOnCheckedChangeListener((buttonView, isChecked) -> {
            acceptButton.setEnabled(isChecked);
        });

        acceptButton.setOnClickListener(v -> {
            if (consentCheckbox.isChecked()) {
                saveConsent();
                proceedToPermissions();
            } else {
                Toast.makeText(this, "Please read and accept the terms to continue", Toast.LENGTH_SHORT).show();
            }
        });

        declineButton.setOnClickListener(v -> {
            Toast.makeText(this, "You must accept the terms to use this app", Toast.LENGTH_LONG).show();
            finish();
        });
    }

    private void loadTermsAndConditions() {
        String terms = "EMPLOYEE MONITORING CONSENT\n\n" +
                "Last Updated: " + java.time.LocalDate.now().toString() + "\n\n" +

                "1. PURPOSE OF MONITORING\n" +
                "This application is designed for legitimate workforce management purposes including:\n" +
                "• Location tracking during work hours for field service coordination\n" +
                "• Call log tracking for customer follow-up and lead management\n" +
                "• App usage monitoring to ensure productivity during work hours\n" +
                "• Lead and task notification management\n\n" +

                "2. WHAT DATA IS COLLECTED\n" +
                "The following data will be collected ONLY during work hours (8:00 AM - 9:00 PM):\n" +
                "• GPS location data\n" +
                "• Call logs (phone numbers, duration, timestamps)\n" +
                "• Installed applications list\n" +
                "• App usage time and statistics\n" +
                "• Lead follow-up activities\n\n" +

                "3. WORK HOURS ENFORCEMENT\n" +
                "Monitoring is AUTOMATICALLY ACTIVE during work hours (8:00 AM - 9:00 PM).\n" +
                "• Tracking starts automatically at 8:00 AM\n" +
                "• Tracking stops automatically at 9:00 PM\n" +
                "• Outside work hours, minimal tracking occurs (only essential notifications)\n" +
                "• You cannot disable tracking during work hours\n\n" +

                "4. VISIBLE INDICATORS\n" +
                "When tracking is active, you will see:\n" +
                "• A persistent notification showing 'Workforce Tracking Active'\n" +
                "• This notification cannot be dismissed during work hours\n" +
                "• The notification ensures transparency about monitoring\n\n" +

                "5. YOUR RIGHTS\n" +
                "• You have the right to know what data is collected\n" +
                "• You can request access to your collected data\n" +
                "• Your personal device data outside work apps is not accessed\n" +
                "• Data is used exclusively for business purposes\n\n" +

                "6. DATA SECURITY\n" +
                "• All data is transmitted securely using HTTPS encryption\n" +
                "• Data is stored on secure company servers\n" +
                "• Access to data is restricted to authorized personnel only\n" +
                "• Data retention follows company policy and local regulations\n\n" +

                "7. COMPANY USE DEVICE\n" +
                "This app should be installed on company-provided devices or with explicit consent on personal devices.\n\n" +

                "8. CONSENT\n" +
                "By checking the box below and clicking 'Accept', you acknowledge that:\n" +
                "• You have read and understood this consent agreement\n" +
                "• You consent to monitoring during work hours (8:00 AM - 9:00 PM)\n" +
                "• You understand tracking will be visible via persistent notification\n" +
                "• You understand this is a condition of your employment\n" +
                "• You can contact HR with any questions or concerns\n\n" +

                "9. CONTACT\n" +
                "For questions about data collection or your rights, contact:\n" +
                "• Company HR Department\n" +
                "• Email: hr@civicgroupbd.com\n" +
                "• Website: https://civicgroupbd.com\n\n" +

                "IMPORTANT: If you decline this consent, you will not be able to use this application.";

        termsTextView.setText(terms);
    }

    private void saveConsent() {
        SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        prefs.edit()
            .putBoolean(KEY_CONSENT_GIVEN, true)
            .putLong(KEY_CONSENT_TIMESTAMP, System.currentTimeMillis())
            .apply();
    }

    private void proceedToPermissions() {
        Intent intent = new Intent(this, PermissionActivity.class);
        startActivity(intent);
        finish();
    }

    @Override
    public void onBackPressed() {
        Toast.makeText(this, "Please accept or decline the terms to continue", Toast.LENGTH_SHORT).show();
    }
}
