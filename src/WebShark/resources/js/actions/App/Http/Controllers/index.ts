import FileController from './FileController'
import PcapController from './PcapController'
import Settings from './Settings'

const Controllers = {
    FileController: Object.assign(FileController, FileController),
    PcapController: Object.assign(PcapController, PcapController),
    Settings: Object.assign(Settings, Settings),
}

export default Controllers