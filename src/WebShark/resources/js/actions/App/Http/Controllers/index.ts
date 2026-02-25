import FileController from './FileController'
import Settings from './Settings'

const Controllers = {
    FileController: Object.assign(FileController, FileController),
    Settings: Object.assign(Settings, Settings),
}

export default Controllers