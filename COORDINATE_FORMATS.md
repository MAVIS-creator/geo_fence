# Coordinate Format Support

Your Geo-Fence Link Generator now supports multiple coordinate formats!

## Supported Formats

### 1. Decimal Degrees (Default)
The most common format used in GPS and mapping applications.

**Examples:**
- `8.165722, 4.265806` (Lagos, Nigeria)
- `40.7128, -74.0060` (New York, USA)
- `-33.8688, 151.2093` (Sydney, Australia)

**How to use:**
- Select "Decimal Degrees" from the format dropdown
- Enter latitude and longitude separately
- Or click on the map / use "Use My Current Location" button

---

### 2. DMS (Degrees, Minutes, Seconds)
Traditional navigation format with N/S/E/W directions.

**Examples:**
- Latitude: `8°09'56.6"N` Longitude: `4°15'56.9"E`
- Latitude: `40°42'46.1"N` Longitude: `74°00'21.6"W`
- Latitude: `33°52'07.7"S` Longitude: `151°12'33.5"E`

**Supported variations:**
- `8°09'56.6"N` (with degree symbol)
- `8 09 56.6 N` (with spaces)
- North/South for latitude, East/West for longitude

**How to use:**
1. Select "DMS" from the format dropdown
2. Enter latitude with N or S direction
3. Enter longitude with E or W direction
4. Tab out or click away to convert and update the map

---

### 3. Plus Codes (Open Location Code)
Google's open-source location encoding system. Find Plus Codes on Google Maps!

**Examples:**
- `6FRR5274+P6` (Full code - Lagos area)
- `87G8Q23M+3M` (Full code - New York)
- `4RRH4R9R+6H` (Full code - Sydney)
- `5274+P6` (Short code - requires nearby reference location)

**Full vs Short Codes:**
- **Full codes** (8+ characters): Can be used anywhere, no reference needed
- **Short codes** (4-7 characters): Require a nearby reference location to recover the full code

**How to get a Plus Code:**
1. Open Google Maps
2. Long-press on any location
3. Click on the coordinates at the bottom
4. Scroll down to see the Plus Code
5. Copy it and paste here!

**Note:** The system automatically detects short codes and will ask for a reference location if needed.

**How to use:**
1. Select "Plus Code" from the format dropdown
2. Enter the complete Plus Code (including the + symbol)
3. For short codes, the system uses your current map view as reference
4. Tab out or click away to convert and update the map

---

## Features

✅ **Automatic Conversion**: All formats convert to decimal degrees internally  
✅ **Map Integration**: Converted coordinates automatically update the map view  
✅ **Real-time Validation**: Invalid formats will show an error message  
✅ **Multiple Input Methods**: Click map, use GPS, or type coordinates  
✅ **Persistent Format**: Your selected format is remembered during the session

---

## Testing Examples

You can test with these real locations:

| Location | Decimal | DMS | Plus Code |
|----------|---------|-----|-----------|
| Lagos, Nigeria | 8.165722, 4.265806 | 8°09'56.6"N 4°15'56.9"E | 6FRR5274+P6 |
| New York, USA | 40.7128, -74.0060 | 40°42'46.1"N 74°00'21.6"W | 87G8Q23M+3M |
| Tokyo, Japan | 35.6762, 139.6503 | 35°40'34.3"N 139°39'01.1"E | 8Q7XM4G2+GR |
| London, UK | 51.5074, -0.1278 | 51°30'26.6"N 0°07'40.0"W | 9C3XGV5C+R4 |

---

## Implementation Details

### PHP Backend (bootstrap.php)
- `dms_to_decimal()`: Converts DMS format to decimal degrees
- `pluscode_to_decimal()`: Converts Plus Codes using open-source library
- `parse_coordinates()`: Smart parser that auto-detects format

### JavaScript Frontend (index.php)
- `parseDMS()`: Client-side DMS parsing for instant feedback
- `parsePlusCode()`: API call to backend for Plus Code conversion
- Format switcher with dynamic input fields

### Open Source Library
- Package: `c3t4r4/openlocationcode`
- Fork of Google's official implementation
- Apache 2.0 License
- No external API calls required for Plus Codes!

---

## Notes

- All coordinate formats work for creating new geo-fenced links
- Existing links always store coordinates as decimal degrees
- Plus Code conversion happens server-side for security and consistency
- DMS conversion happens client-side for instant feedback
- Invalid formats will show clear error messages with examples

Enjoy your enhanced coordinate input system! 🗺️✨
