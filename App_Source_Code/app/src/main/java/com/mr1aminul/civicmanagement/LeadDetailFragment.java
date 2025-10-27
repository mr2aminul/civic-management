package com.mr1aminul.civicmanagement;

import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;

public class LeadDetailFragment extends Fragment {

    private static final String ARG_NAME = "name";
    private static final String ARG_PHONE = "phone";
    private static final String ARG_EMAIL = "email";
    private static final String ARG_ADDRESS = "address";

    private String name, phone, email, address;

    public LeadDetailFragment() {}

    public static LeadDetailFragment newInstance(String name, String phone, String email, String address) {
        LeadDetailFragment fragment = new LeadDetailFragment();
        Bundle args = new Bundle();
        args.putString(ARG_NAME, name);
        args.putString(ARG_PHONE, phone);
        args.putString(ARG_EMAIL, email);
        args.putString(ARG_ADDRESS, address);
        fragment.setArguments(args);
        return fragment;
    }

    @Override
    public void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        if (getArguments() != null) {
            name = getArguments().getString(ARG_NAME);
            phone = getArguments().getString(ARG_PHONE);
            email = getArguments().getString(ARG_EMAIL);
            address = getArguments().getString(ARG_ADDRESS);
        }
    }

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container,
                             @Nullable Bundle savedInstanceState) {
        View view = inflater.inflate(R.layout.fragment_lead_detail, container, false);
        ((TextView) view.findViewById(R.id.leadName)).setText(name);
        ((TextView) view.findViewById(R.id.leadPhone)).setText(phone);
        ((TextView) view.findViewById(R.id.leadEmail)).setText(email);
        ((TextView) view.findViewById(R.id.leadKatha)).setText(address);
        return view;
    }
}
