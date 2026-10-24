// Ported from mobile src/utils/pitchCoordinateMap.js for matching saved pitch coordinates.
/**
 * Pitch Coordinate Mapping for Catcher Image
 * Maps tap coordinates (points 1-3600) to strike/ball zones and pitch side locations
 * 
 * 60x60 grid layout:
 * - Strike zone: rows 15-45, columns 16-45
 * - Position codes: T/M/B (top/middle/bottom), L/C/R (left/center/right)
 */

/**
 * Generate pitch coordinate data based on grid position
 */
const generateCoordinateData = () => {
    const data = [];

    // Strike zone boundaries
    const MIN_ROW = 15;
    const MAX_ROW = 45;
    const MIN_COL = 16;
    const MAX_COL = 45;

    for (let point = 1; point <= 3600; point++) {
        // Calculate row and column from point number (1-based)
        const row = Math.ceil(point / 60); // 1-60
        const col = ((point - 1) % 60) + 1; // 1-60

        // Determine if strike zone
        const isStrike = col >= MIN_COL && col <= MAX_COL && row >= MIN_ROW && row <= MAX_ROW;

        // Determine vertical position (T/M/B) based on thirds of strike zone
        let verticalPos;
        if (isStrike) {
            const verticalThird = (MAX_ROW - MIN_ROW + 1) / 3;
            if (row >= MIN_ROW && row < MIN_ROW + verticalThird) verticalPos = 'T';
            else if (row >= MIN_ROW + verticalThird && row < MIN_ROW + (verticalThird * 2)) verticalPos = 'M';
            else verticalPos = 'B';
        } else {
            // Outside strike zone
            if (row < MIN_ROW) verticalPos = 'T';
            else if (row > MAX_ROW) verticalPos = 'B';
            else verticalPos = 'M';
        }

        // Determine horizontal position (L/C/R) based on thirds of strike zone
        let horizontalPos;
        if (isStrike) {
            const horizontalThird = (MAX_COL - MIN_COL + 1) / 3;
            if (col >= MIN_COL && col < MIN_COL + horizontalThird) horizontalPos = 'L';
            else if (col >= MIN_COL + horizontalThird && col < MIN_COL + (horizontalThird * 2)) horizontalPos = 'C';
            else horizontalPos = 'R';
        } else {
            // Outside strike zone
            if (col < MIN_COL) horizontalPos = 'L';
            else if (col > MAX_COL) horizontalPos = 'R';
            else horizontalPos = 'C';
        }

        const position = verticalPos + horizontalPos;

        data.push({
            point: point,
            row: row,
            col: col,
            position: position,
            strike: {
                status: isStrike,
                position: isStrike ? position : ''
            }
        });
    }

    return data;
};

export const dataCoordinates = generateCoordinateData();

/**
 * Get pitch information from coordinate map
 * @param {number} pitch_mark - The tap point coordinate (1-3600)
 * @returns {object} { is_strike: boolean, pitch_side: string, zone: string }
 */
export const getPitchInfoFromCoordinate = (pitch_mark) => {
    if (!pitch_mark || pitch_mark < 1 || pitch_mark > 3600) {
        return { is_strike: false, pitch_side: '', zone: 'B' };
    }

    // Find the matching coordinate entry
    const coordinate = dataCoordinates.find(c => c.point === pitch_mark);

    if (!coordinate) {
        return { is_strike: false, pitch_side: '', zone: 'B' };
    }

    const is_strike = coordinate.strike.status;
    const pitch_side = is_strike ? coordinate.strike.position : coordinate.position;
    const zone = is_strike ? 'S' : 'B';

    return {
        is_strike,
        pitch_side,
        zone
    };
};

