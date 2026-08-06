# 📊 Complete Risk Assessment System - User Guide

## Overview
This system calculates **Composite Risk on Business Activities** by combining:
- **60% Quantitative Data** (Inherent Risks)
- **40% Qualitative Data** (Risk Controls and Mitigants)

---

## 🎯 Three-Part Process

### **PART 1: Quantitative Data - Inherent Risks (60%)**

This section displays automatically based on data already entered in your system:

#### Risk Categories Measured:
1. **Client Risk** - Natural Person (Resident/Non-Resident), Legal Person (Resident/Non-Resident)
2. **PEPs Risk** - Domestic and Foreign Politically Exposed Persons
3. **Delivery Channel Risk** - Over the Counter vs Non-Face to Face
4. **Geographic Location Risk** - Rural, Urban, Kathmandu, Border Areas

#### Activities Covered:
- **Stock Broker Services** (Weight: 100%)
  - Share Transaction (55%)
  - Commercial Debenture/Bond Transaction (10%)
  - Government Bond Transaction (10%)
  - Mutual Fund Transaction (20%)
  - Margin Service (5%)

- **Issue & Sales Management Service** (Weight: 40%)
  - IPO - General Public (5%)
  - IPO - Employees (25%)
  - IPO - Local People (10%)
  - IPO - PF/CIT/Others (20%)
  - FPO (15%)
  - Private Placement (5%)
  - Offer Document (5%)
  - Right Share (5%)
  - Auction Share (10%)

- **Portfolio Management Services** (Weight: 60%)
  - Discretionary (60%)
  - Non-Discretionary (10%)
  - Advisory (10%)
  - Return Guarantee (20%)

- **Business Risk for Other Services** (Weight: 100%)
  - Demat Account Count (40%)

#### Calculation Method:
- **Total Inherent Risk** = AVERAGEIF(Client Risk, PEPs Risk, Delivery Channel Risk, Geographic Risk, Other Risks)
  - Only non-zero values are included in the average
  - If all values are 0, Total Inherent Risk = 0

---

### **PART 2: Qualitative Data Entry - Risk Controls and Mitigants (40%)**

This is where you **ENTER DATA** by filling out the form.

#### Seven Risk Assessment Factors:

1. **Corporate Governance (30%)** - Board oversight, management structure, ethical policies
2. **Policies and Procedures (10%)** - Written policies, standard operating procedures
3. **Risk Management (20%)** - Risk identification, assessment, mitigation processes
4. **Internal Controls (15%)** - Internal audit, control mechanisms
5. **Compliance Function (15%)** - AML/CFT compliance, regulatory adherence
6. **Training (5%)** - Staff training programs, awareness initiatives
7. **Reporting and Record Keeping (5%)** - Documentation, reporting systems

#### Scoring Scale:
- **1.00 - 1.50**: Very Good
- **1.51 - 2.50**: Good
- **2.51 - 3.50**: Acceptable
- **3.51 - 4.00**: Deficient
- **4.01 - 5.00**: Very Deficient

#### Calculation:
**Total RAS** = Weighted average of all 7 factors
```
Total RAS = (CorporateGov × 30% + Policies × 10% + RiskMgmt × 20% + 
             InternalControls × 15% + Compliance × 15% + Training × 5% + 
             Reporting × 5%) / 100
```

---

### **PART 3: Composite Risk on Business Activities**

This section appears **AFTER** you save the Qualitative Data.

#### Formula:
```
Composite Risk = IF(Total Inherent Risk = 0, 0, 
                    (Total Inherent Risk × 60%) + (Total RAS × 40%))
```

#### Example Calculation:
- **Total Inherent Risk** = 2.93 (from Quantitative Data)
- **Total RAS** = 3.54 (from Qualitative Data)
- **Composite Risk** = (2.93 × 0.60) + (3.54 × 0.40)
- **Composite Risk** = 1.758 + 1.416 = **3.174**

---

## 📝 Step-by-Step Usage

### Step 1: Select Reporting Entity & Fiscal Year
- Choose from dropdown menus
- Click "View Data"

### Step 2: Review Quantitative Data (PART 1)
- Table shows all inherent risks automatically
- Data comes from previously entered transaction data
- Review Total Inherent Risk for each activity

### Step 3: Enter Qualitative Data (PART 2)
- Fill in all 7 risk assessment fields (1.00 - 5.00)
- Watch Total RAS calculate in real-time
- Click "SAVE QUALITATIVE DATA"
- Success message confirms save

### Step 4: Review Composite Risk (PART 3)
- Automatically appears after saving Qualitative Data
- Shows combined risk score for each activity
- Formula: 60% Quantitative + 40% Qualitative

---

## 🎨 Color Coding

### Inherent Risk (Quantitative):
- **Green (#27ae60)**: Very Low / Low Risk (1.00 - 2.50)
- **Orange (#f39c12)**: Medium Risk (2.51 - 3.50)
- **Red (#e74c3c)**: High / Very High Risk (3.51 - 5.00)

### Risk Controls (Qualitative):
- **Green**: Very Good / Good (1.00 - 2.50)
- **Orange**: Acceptable (2.51 - 3.50)
- **Red**: Deficient / Very Deficient (3.51 - 5.00)

### Composite Risk:
- **Red Background (#e74c3c)**: Final calculated risk
- Always displayed prominently

---

## 💡 Important Notes

1. **Quantitative Data** is read-only on this page - it comes from your transaction entry forms
2. **Qualitative Data** can be updated anytime - just re-enter and save
3. **Composite Risk** recalculates automatically when you save new Qualitative Data
4. Each Reporting Entity + Fiscal Year combination has unique Qualitative Data
5. All fields in Qualitative Data are **required** (must be between 1.00 and 5.00)

---

## 🔧 Database Requirements

Ensure this table exists:

```sql
CREATE TABLE RiskControlsAndMitigants (
    ID INT PRIMARY KEY IDENTITY(1,1),
    MarketParticipant_id INT NOT NULL,
    FiscalYear_id INT NOT NULL,
    CorporateGovernance DECIMAL(10,2),
    PoliciesProcedures DECIMAL(10,2),
    RiskManagement DECIMAL(10,2),
    InternalControls DECIMAL(10,2),
    ComplianceFunction DECIMAL(10,2),
    Training DECIMAL(10,2),
    ReportingRecordKeeping DECIMAL(10,2),
    CreatedDate DATETIME DEFAULT GETDATE(),
    ModifiedDate DATETIME DEFAULT GETDATE(),
    CONSTRAINT UQ_RiskControls_MP_FY UNIQUE (MarketParticipant_id, FiscalYear_id)
);
```

---

## 📊 Reporting Features

The system provides:
- ✅ Activity-level risk breakdown
- ✅ Section-level aggregation
- ✅ SEBON weight application
- ✅ Real-time calculation
- ✅ Trend indicators
- ✅ Historical comparison capability

---

## 🆘 Troubleshooting

**Issue**: Qualitative Data form doesn't appear
- **Solution**: Ensure Reporting Entity and Fiscal Year are selected

**Issue**: Composite Risk table doesn't show
- **Solution**: Save Qualitative Data first by clicking "SAVE QUALITATIVE DATA"

**Issue**: Can't save Qualitative Data
- **Solution**: Check all values are between 1.00 and 5.00

**Issue**: Total RAS shows 0.00
- **Solution**: Enter values in at least one Qualitative Data field

---

## 📈 Best Practices

1. **Regular Updates**: Update Qualitative Data quarterly or annually
2. **Consistent Scoring**: Use the same assessment criteria across all Reporting Entities
3. **Documentation**: Keep notes on why specific scores were assigned
4. **Trend Analysis**: Compare current vs previous periods
5. **Action Plans**: High composite risk (>3.50) should trigger mitigation strategies

---

## 📞 Support

For technical issues or questions about risk calculations:
- Review this guide
- Check database connections
- Verify data entry in source tables
- Consult with risk management team for scoring guidance

---

**Version**: 1.0  
**Last Updated**: November 2025  
**System**: Risk Matrix AML Assessment Platform